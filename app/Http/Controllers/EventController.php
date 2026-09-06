<?php
namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Location;
use App\Models\Package;
use App\Models\ProviderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EventController extends Controller
{

    public function createEvent(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'budget'       => 'nullable|numeric|min:0',
            'event_date'   => 'required|date|after_or_equal:today',
            'type'         => 'nullable|in:event,standalone',
            'start_time'   => 'required|date_format:H:i',
            'end_time'     => 'required|date_format:H:i|after:start_time',
            'city_id'      => 'required|exists:cities,id',
            'address_name' => 'required|string|max:255',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            $location = Location::create([
                'latitude'     => $request->latitude,
                'longitude'    => $request->longitude,
                'city_id'      => $request->city_id,
                'address_name' => $request->address_name,
            ]);

            $event = Event::create([
                'user_id'      => Auth::id(),
                'name'         => $request->name,
                'budget'       => $request->budget,
                'event_date'   => $request->event_date,
                'start_time'   => $request->start_time,
                'end_time'     => $request->end_time,
                'type'         => $request->type ?? 'event',
                'status'       => 'upcoming',
                'location_id'  => $location->id,
                'total_amount' => 0,
            ]);

            DB::commit();

            return response()->json([
                'message'   => __('messages.event_created'),
                'event'     => $event->load('location.city'),
                'budget'    => $event->budget,
                'spent'     => 0,
                'remaining' => $event->budget ?? 0,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => __('messages.event_creation_error'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ===== دالة مساعدة لإضافة أسماء العناصر إلى options =====
     */
    private function enrichOptionsWithItemNames($options, $service)
    {
        if (empty($options) || ! is_array($options)) {
            return $options;
        }

        $features = $service->features ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        foreach ($options as &$option) {
            if (isset($option['selectedDecorIds']) && is_array($option['selectedDecorIds']) && ! isset($option['selectedDecorNames'])) {
                $decorNames = [];
                foreach ($option['selectedDecorIds'] as $decorId) {
                    $name = null;
                    if (! empty($features['decoration_options'])) {
                        foreach ($features['decoration_options'] as $item) {
                            if ($item['id'] == $decorId) {
                                $name = $item['name'] ?? $decorId;
                                break;
                            }
                        }
                    }
                    $decorNames[] = $name ?: $decorId;
                }
                $option['selectedDecorNames'] = $decorNames;
            }

            if (isset($option['selectedKoshaIds']) && is_array($option['selectedKoshaIds']) && ! isset($option['selectedKoshaNames'])) {
                $koshaNames = [];
                foreach ($option['selectedKoshaIds'] as $koshaId) {
                    $name = null;
                    if (! empty($features['kosha_items'])) {
                        foreach ($features['kosha_items'] as $item) {
                            if ($item['id'] == $koshaId) {
                                $name = $item['name'] ?? $koshaId;
                                break;
                            }
                        }
                    }
                    $koshaNames[] = $name ?: $koshaId;
                }
                $option['selectedKoshaNames'] = $koshaNames;
            }
        }

        return $options;
    }

    /**
     * ===== دالة مساعدة لحساب السعر من الخيارات (معدلة بالكامل) =====
     * ✅ تستخرج السعر مباشرة من options (totalPrice, koshaDetails, decorDetails)
     * ✅ لم تعد تعتمد على features المخزنة في قاعدة البيانات
     */
    private function calculatePriceFromOptions($options, $service = null)
    {
        $totalPrice = 0;
        if (empty($options) || ! is_array($options)) {
            return $totalPrice;
        }

        foreach ($options as $option) {
            // 1. البحث عن totalPrice مباشرة (أولوية قصوى)
            if (! empty($option['totalPrice']) && is_numeric($option['totalPrice'])) {
                $totalPrice += floatval($option['totalPrice']);
                continue; // تخطي باقي الحسابات لهذا الخيار لأن totalPrice شامل
            }

            // 2. جمع أسعار koshaDetails (إن وجدت)
            if (! empty($option['koshaDetails']) && is_array($option['koshaDetails'])) {
                foreach ($option['koshaDetails'] as $item) {
                    if (isset($item['price']) && is_numeric($item['price'])) {
                        $totalPrice += floatval($item['price']);
                    }
                }
            }

            // 3. جمع أسعار decorDetails (إن وجدت)
            if (! empty($option['decorDetails']) && is_array($option['decorDetails'])) {
                foreach ($option['decorDetails'] as $item) {
                    if (isset($item['price']) && is_numeric($item['price'])) {
                        $totalPrice += floatval($item['price']);
                    }
                }
            }

            // 4. (احتياطي) البحث عن price داخل option نفسه
            if (! empty($option['price']) && is_numeric($option['price'])) {
                $totalPrice += floatval($option['price']);
            }
        }

        return round($totalPrice, 2);
    }



    public function bookService(Request $request)
    {
        $packageId         = $request->input('package_id') ?? $request->package_id;
        $providerServiceId = $request->input('provider_service_id') ?? $request->provider_service_id;

        if (! $providerServiceId && $packageId) {
            $package = Package::find($packageId);
            if ($package) {
                $relationName = method_exists($package, 'providerServices') ? 'providerServices' : 'services';
                $firstService = $package->$relationName()->first();

                if ($firstService) {
                    if (get_class($firstService) === 'App\Models\ProviderService') {
                        $providerServiceId = $firstService->id;
                    } else {
                        $providerServiceId = ProviderService::where('service_id', $firstService->id)->value('id');
                    }
                } else {
                    $providerServiceId = $package->provider_service_id ?? $package->service_id ?? null;
                }
            }
        }

        $service = ProviderService::with('service')->find($providerServiceId);

        if (! $service || (! $service->service && ! $packageId)) {
            return response()->json([
                'message' => __('messages.service_unavailable'),
            ], 404);
        }

        $isEventBooking  = $request->filled('event_id');
        $isStandalone    = ! $isEventBooking && $request->filled('event_date');
        $isTicketBooking = ! $isEventBooking && ! $isStandalone;

        $isFixedPriceService = $service->service ? ($service->service->service_type === 'cake' || $service->service->service_type === 'public_event') : false;

        if ($service && $service->service && $service->service->service_type === 'public_event') {
            $isTicketBooking     = true;
            $isFixedPriceService = true;
        }

        $rules = [
            'provider_service_id' => 'nullable|exists:provider_services,id',
            'package_id'          => 'nullable|exists:packages,id',
            'options'             => 'nullable|array',
            'quantity'            => 'nullable|integer|min:1',
            'selected_image_id'   => 'nullable|exists:service_images,id',
        ];

        if ($isStandalone) {
            $rules['event_date'] = 'required|date|after_or_equal:today';
            $rules['start_time'] = 'required|date_format:H:i';
            $isPublicEvent       = $service && $service->service && $service->service->service_type === 'public_event';
            $rules['end_time']   = ($isFixedPriceService && ! $packageId) || $isPublicEvent
                ? 'nullable|date_format:H:i'
                : 'required|date_format:H:i|after:start_time';
            $rules['city_id']      = 'required|exists:cities,id';
            $rules['address_name'] = 'required|string|max:255';
            $rules['latitude']     = 'required|numeric';
            $rules['longitude']    = 'required|numeric';
        } elseif ($isEventBooking) {
            $rules['event_id'] = 'required|exists:events,id';
        }

        $request->validate($rules);

        try {
            $response = DB::transaction(function () use ($request, $providerServiceId, $packageId, $service, $isFixedPriceService, $isEventBooking, $isStandalone, $isTicketBooking) {

                $event = null;

                if ($isEventBooking) {
                    $event = Event::findOrFail($request->event_id);
                    if ($event->user_id != Auth::id()) {
                        throw new HttpException(403, __('messages.unauthorized_booking'));
                    }
                    if (in_array($event->status, ['completed', 'cancelled'])) {
                        throw new HttpException(422, __('messages.booking_not_allowed'));
                    }
                    $startTime = $event->start_time ?? '00:00';
                    $endTime   = $event->end_time ?? '00:00';
                    $eventDate = $event->event_date ?? now()->toDateString();
                } elseif ($isStandalone) {
                    $location = Location::firstOrCreate(
                        ['latitude' => $request->latitude, 'longitude' => $request->longitude],
                        ['city_id' => $request->city_id, 'address_name' => $request->address_name]
                    );
                    $eventName = $packageId ? 'حجز باقة فردي' : 'حجز فردي - ' . $service->service->name;
                    $startTime = $request->start_time;
                    $endTime   = ($isFixedPriceService && ! $packageId) ? '00:00' : $request->end_time;
                    $eventDate = $request->event_date;
                    $event     = Event::create([
                        'user_id'        => Auth::id(),
                        'name'           => $eventName,
                        'budget'         => null,
                        'event_date'     => $eventDate,
                        'start_time'     => $startTime,
                        'end_time'       => $endTime,
                        'type'           => 'standalone',
                        'status'         => 'upcoming',
                        'location_id'    => $location->id,
                        'payment_status' => 'pending_payment',
                        'total_amount'   => 0,
                    ]);
                } elseif ($isTicketBooking) {
                    $features = $service->features;
                    if (is_string($features)) {
                        $features = json_decode($features, true);
                    }

                    $location = Location::firstOrCreate(
                        ['latitude' => $request->latitude ?? 0, 'longitude' => $request->longitude ?? 0],
                        ['city_id' => $request->city_id ?? 1, 'address_name' => $request->address_name ?? 'موقع التذكرة']
                    );

                    $eventName = 'حجز تذكرة - ' . $service->service->name;
                    $startTime = $request->start_time ?? $features['start_time'] ?? '00:00';
                    $endTime   = $request->end_time ?? $features['end_time'] ?? '00:00';
                    $eventDate = $request->event_date ?? $features['event_date'] ?? now()->toDateString();

                    $event = Event::create([
                        'user_id'        => Auth::id(),
                        'name'           => $eventName,
                        'budget'         => null,
                        'event_date'     => $eventDate,
                        'start_time'     => $startTime,
                        'end_time'       => $endTime,
                        'type'           => 'standalone',
                        'status'         => 'upcoming',
                        'location_id'    => $location->id,
                        'payment_status' => 'pending_payment',
                        'total_amount'   => 0,
                    ]);
                }

                $totalPrice   = 0;
                $isFixedPrice = false;
                $quantity     = max(1, (int) $request->input('quantity', 1));

                if ($isTicketBooking) {
                    $features = $service->features;
                    if (is_string($features)) {
                        $features = json_decode($features, true);
                    }
                    $availableTickets = $features['ticket_count'] ?? $service->capacity ?? 0;
                    if ($availableTickets < $quantity) {
                        throw new HttpException(422, __('messages.tickets_unavailable'));
                    }
                }

                if ($packageId) {
                    $package = $service->packages()->where('packages.id', $packageId)->first();
                    if (! $package) {
                        throw new HttpException(422, __('messages.package_not_found'));
                    }

                    if (! $startTime || ! $endTime) {
                        throw new HttpException(422, __('messages.time_required_package'));
                    }

                    $hours      = Carbon::parse($startTime)->floatDiffInHours(Carbon::parse($endTime));
                    $totalPrice = round($hours * (float) $package->price, 2);
                } elseif ($isTicketBooking) {
                    $ticketPrice  = $service->features['ticket_price'] ?? $service->price ?? 0;
                    $totalPrice   = round((float) $ticketPrice * $quantity, 2);
                    $isFixedPrice = true;
                } elseif ($isFixedPriceService) {
                    // ✅ نبحث عن الصورة/التصميم المختار جوا options[] (الفرونت
                    // بيبعتها هيك، مش كـ selected_image_id مستقلة بالـ top level)
                    $selectedImageOption = null;

                    if ($request->filled('options')) {
                        foreach ($request->options as $optionData) {
                            if (($optionData['id'] ?? null) === 'selected_image' && ! empty($optionData['is_selected'])) {
                                $selectedImageOption = $optionData;
                                break;
                            }
                        }
                    }

                    if ($service->service->service_type === 'cake' && $selectedImageOption) {
                        $imagePrice  = $selectedImageOption['price'] ?? $selectedImageOption['unit_price'] ?? 0;
                        $imgQuantity = max(1, (int) ($selectedImageOption['quantity'] ?? $quantity));

                        $totalPrice = round((float) $imagePrice * $imgQuantity, 2);
                    } else {
                        $totalPrice = round((float) $service->price * $quantity, 2);
                    }
                    $isFixedPrice = true;
                }
                // ✅ التعديل هنا: استخدام الدالة المعدلة calculatePriceFromOptions
                elseif (in_array($service->service->service_type, ['decoration', 'planner'])) {
                    $options    = $request->input('options', []);
                    $totalPrice = $this->calculatePriceFromOptions($options, $service);
                    // ✅ إذا كان السعر لا يزال 0، حاول استخراجه من totalPrice في الخيار الأول
                    if ($totalPrice == 0 && ! empty($options[0]['totalPrice'])) {
                        $totalPrice = floatval($options[0]['totalPrice']);
                    }
                    $isFixedPrice = true;
                } else {
                    if (! $startTime || ! $endTime) {
                        throw new HttpException(422, __('messages.time_required'));
                    }

                    $hours      = Carbon::parse($startTime)->floatDiffInHours(Carbon::parse($endTime));
                    $totalPrice = round($hours * $service->price, 2);
                }

                if ($request->filled('options')) {
                    foreach ($request->options as $optionData) {
                        if (! empty($optionData['is_selected'])) {
                            // ✅ نتخطى selected_image هون لأنو انضاف فوق أصلاً
                            // (ضمن بلوك isFixedPriceService / calculatePriceFromOptions)
                            // منعاً لتكرار احتساب سعرها مرتين (double counting)
                            if (($optionData['id'] ?? null) === 'selected_image') {
                                continue;
                            }

                            $optionPrice = 0;
                            if (isset($optionData['id']) && is_numeric($optionData['id']) && Schema::hasTable('service_options')) {
                                $optionPrice = DB::table('service_options')->where('id', $optionData['id'])->value('price') ?? 0;
                            } else {
                                $optionPrice = $optionData['price'] ?? 0;
                            }

                            $optQuantity  = max(1, (int) ($optionData['quantity'] ?? 1));
                            $totalPrice  += (float) $optionPrice * $optQuantity;
                        }
                    }
                }

                if ($isEventBooking && ! $isFixedPrice && ! $isTicketBooking) {
                    if (Carbon::parse($startTime)->lt(Carbon::parse($event->start_time)) || Carbon::parse($endTime)->gt(Carbon::parse($event->end_time))) {
                        throw new HttpException(422, __('messages.time_within_event'));
                    }

                    $exclusiveTypes = ['hall', 'photographer', 'music'];
                    if (in_array($service->service->service_type, $exclusiveTypes)) {
                        $existingItem = EventItem::where('event_id', $event->id)
                            ->where('provider_service_id', $providerServiceId)
                            ->whereNotIn('status', ['cancelled', 'rejected'])
                            ->first();
                        if ($existingItem) {
                            if ($packageId && is_null($existingItem->package_id)) {
                                $event->decrement('total_amount', $existingItem->price);
                                $existingItem->delete();
                            } else {
                                throw new HttpException(409, __('messages.already_booked', ['service_name' => $service->service->name]));
                            }
                        }
                    }
                }

                if (! $isFixedPrice && ! $isTicketBooking && $eventDate && $startTime && $endTime) {
                    $conflict = EventItem::where('provider_service_id', $providerServiceId)
                        ->whereIn('status', ['pending', 'approved'])
                        ->where('event_id', '!=', $event->id)
                        ->whereHas('event', function ($q) use ($eventDate) {
                            $q->where('event_date', $eventDate);
                        })
                        ->where(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        })
                        ->exists();
                    if ($conflict) {
                        throw new HttpException(409, __('messages.conflict'));
                    }
                }

                if ($isEventBooking && ! is_null($event->budget) && $event->budget > 0) {
                    $currentTotal  = $event->eventItems()->whereIn('status', ['pending', 'approved'])->sum('price');
                    $expectedTotal = $currentTotal + $totalPrice;
                    if ($expectedTotal > $event->budget) {
                        throw new HttpException(422, __('messages.budget_exceeded'));
                    }
                }

                if ($isTicketBooking) {
                    $features = $service->features;
                    if (is_string($features)) {
                        $features = json_decode($features, true);
                    }
                    if (isset($features['ticket_count'])) {
                        $features['ticket_count'] -= $quantity;
                        $service->features         = $features;
                        $service->save();
                    } elseif ($service->capacity !== null) {
                        $service->decrement('capacity', $quantity);
                    }
                }

                $enrichedOptions = $request->options ?? [];
                if (! empty($enrichedOptions) && $service) {
                    $enrichedOptions = $this->enrichOptionsWithItemNames($enrichedOptions, $service);
                }

                // ✅ إضافة جديدة: تخزين بيانات الصورة المختارة (لو موجودة) جوا
                // enrichedOptions حتى نقدر نعرضها لاحقاً بدون join إضافي
                if ($request->filled('selected_image_id')) {
                    $enrichedOptions['selected_image_id'] = $request->selected_image_id;

                    $imageData = DB::table('service_images')
                        ->where('id', $request->selected_image_id)
                        ->first();
                    if ($imageData) {
                        $enrichedOptions['selected_image_path']  = $imageData->image_path;
                        $enrichedOptions['selected_image_price'] = $imageData->price;
                    }
                }

                $paymentStatus = 'pending';
                if (! empty($enrichedOptions) && is_array($enrichedOptions)) {
                    foreach ($enrichedOptions as $option) {
                        if (is_array($option) && ! empty($option['paymentMethod'])) {
                            $method = strtolower($option['paymentMethod']);
                            if ($method === 'cash') {
                                $paymentStatus = 'pending_cash';
                            } else {
                                $paymentStatus = 'pending_payment';
                            }
                            break;
                        }
                    }
                }

                $item = $event->eventItems()->create([
                    'provider_service_id' => $providerServiceId,
                    'package_id'          => $packageId,
                    'price'               => $totalPrice,
                    'start_time'          => $startTime ?? '00:00',
                    'end_time'            => $endTime ?? '23:59',
                    'payment_status'      => $paymentStatus,
                    'status'              => 'pending',
                    'option'              => $enrichedOptions,
                    'delivery_type'       => $request->delivery_type,
                    'notes'               => $request->notes,
                ]);

                $event->increment('total_amount', $totalPrice);
                $event->refresh();

                $item->load([
                    'event.location.city',
                    'providerService.service',
                    'providerService.images',
                ]);

                return [
                    'item'        => $item,
                    'total_price' => $totalPrice,
                    'event_total' => $event->total_amount,
                    'budget'      => $event->budget,
                ];
            });

            return response()->json([
                'message'     => __('messages.booking_success'),
                'total_price' => $response['total_price'],
                'event_total' => $response['event_total'],
                'budget'      => $response['budget'],
                'spent'       => $response['event_total'],
                'remaining'   => $response['budget'] ? $response['budget'] - $response['event_total'] : null,
                'item'        => $response['item'],
            ], 201);

        } catch (\Throwable $e) {
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.booking_error') : $e->getMessage(),
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    public function updateEvent(Request $request, $id)
    {
        try {
            $response = DB::transaction(function () use ($request, $id) {

                $event = Event::with(['eventItems.providerService.service'])->findOrFail($id);

                if ($event->user_id != Auth::id()) {
                    throw new HttpException(403, __('messages.unauthorized_update'));
                }

                if (in_array($event->status, ['completed', 'cancelled'])) {
                    throw new HttpException(422, __('messages.event_cannot_be_updated'));
                }

                $request->validate([
                    'name'         => 'sometimes|string|max:255',
                    'budget'       => 'nullable|numeric|min:0',
                    'event_date'   => 'sometimes|date|after_or_equal:today',
                    'start_time'   => 'sometimes|date_format:H:i',
                    'end_time'     => 'sometimes|date_format:H:i|after:start_time',
                    'city_id'      => 'sometimes|exists:cities,id',
                    'address_name' => 'sometimes|string|max:255',
                    'latitude'     => 'sometimes|numeric',
                    'longitude'    => 'sometimes|numeric',
                ]);

                if ($request->hasAny(['city_id', 'address_name', 'latitude', 'longitude'])) {
                    $location = $event->location;
                    if (! $location) {
                        $location = Location::create([
                            'city_id'      => $request->city_id,
                            'address_name' => $request->address_name,
                            'latitude'     => $request->latitude,
                            'longitude'    => $request->longitude,
                        ]);
                        $event->location_id = $location->id;
                    } else {
                        $location->update([
                            'city_id'      => $request->input('city_id', $location->city_id),
                            'address_name' => $request->input('address_name', $location->address_name),
                            'latitude'     => $request->input('latitude', $location->latitude),
                            'longitude'    => $request->input('longitude', $location->longitude),
                        ]);
                    }
                }

                $newEventDate  = $request->input('event_date', $event->event_date);
                $newStartTime  = $request->input('start_time', $event->start_time);
                $newEndTime    = $request->input('end_time', $event->end_time);
                $isTimeChanged = $request->hasAny(['event_date', 'start_time', 'end_time']);

                $event->update([
                    'name'       => $request->input('name', $event->name),
                    'budget'     => $request->has('budget') ? $request->budget : $event->budget,
                    'event_date' => $newEventDate,
                    'start_time' => $newStartTime,
                    'end_time'   => $newEndTime,
                ]);

                if ($isTimeChanged && $event->eventItems->isNotEmpty()) {
                    foreach ($event->eventItems as $item) {
                        $service = $item->providerService;
                        $isCake  = $service->service->service_type === 'cake';

                        $itemStartTime = $newStartTime;
                        $itemEndTime   = $isCake ? null : $newEndTime;
                        $itemPrice     = $item->price;

                        if (! $isCake) {
                            $conflict = EventItem::where('provider_service_id', $item->provider_service_id)
                                ->where('id', '!=', $item->id)
                                ->where('event_id', '!=', $event->id)
                                ->whereIn('status', ['pending', 'approved'])
                                ->whereHas('event', function ($q) use ($newEventDate) {
                                    $q->where('event_date', $newEventDate);
                                })
                                ->where(function ($q) use ($itemStartTime, $itemEndTime) {
                                    $q->where('start_time', '<', $itemEndTime)
                                        ->where('end_time', '>', $itemStartTime);
                                })
                                ->exists();

                            if ($conflict) {
                                throw new HttpException(409, __('messages.time_conflict_service', ['service_name' => $service->service->name]));
                            }

                            if (is_null($item->package_id)) {
                                $hours     = Carbon::parse($itemStartTime)->floatDiffInHours(Carbon::parse($itemEndTime));
                                $itemPrice = round($hours * (float) $service->price, 2);

                                $oldOptions = is_string($item->option) ? json_decode($item->option, true) : ($item->option ?? []);
                                foreach ($oldOptions as $option) {
                                    if (! empty($option['is_selected'])) {
                                        $itemPrice += (float) ($option['price'] ?? 0) * max(1, (int) ($option['quantity'] ?? 1));
                                    }
                                }
                                $itemPrice = round($itemPrice, 2);
                            }
                        }

                        $item->update([
                            'start_time' => $itemStartTime,
                            'end_time'   => $itemEndTime,
                            'price'      => $itemPrice,
                        ]);
                    }
                }

                $totalCalculatedAmount = $event->eventItems()
                    ->whereIn('status', ['pending', 'approved'])
                    ->sum('price');

                if (! is_null($event->budget) && $event->budget > 0) {
                    if ($totalCalculatedAmount > $event->budget) {
                        throw new HttpException(422, __('messages.budget_insufficient_for_update'));
                    }
                }

                $event->update([
                    'total_amount' => $totalCalculatedAmount,
                ]);

                return $event->fresh(['eventItems.providerService.service', 'location.city']);
            });

            return response()->json([
                'message' => __('messages.event_updated_success'),
                'event'   => $response,
            ], 200);

        } catch (\Throwable $e) {
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.event_update_error') : $e->getMessage(),
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    public function updateBooking(Request $request, $id)
    {
        try {
            $response = DB::transaction(function () use ($request, $id) {

                $item    = EventItem::with(['event', 'providerService.service'])->findOrFail($id);
                $service = $item->providerService;
                $event   = $item->event;

                $isCake       = $service->service->service_type === 'cake';
                $isStandalone = $event->type === 'standalone';

                if ($item->payment_status === 'paid') {
                    throw new HttpException(403, __('messages.cannot_update_paid_booking'));
                }

                if ($isCake && $item->status === 'approved') {
                    throw new HttpException(403, __('messages.cannot_update_approved_service'));
                }

                if (in_array(trim($item->status), ['rejected', 'cancelled'])) {
                    throw new HttpException(403, __('messages.cannot_update_cancelled_booking'));
                }

                $request->validate([
                    'event_date'   => 'sometimes|date',
                    'start_time'   => 'sometimes|date_format:H:i',
                    'end_time'     => 'nullable|date_format:H:i',
                    'options'      => 'sometimes|array',
                    'city_id'      => 'sometimes|exists:cities,id',
                    'address_name' => 'sometimes|string|max:255',
                    'latitude'     => 'sometimes|numeric',
                    'longitude'    => 'sometimes|numeric',
                    'package_id'   => 'nullable|exists:packages,id',
                ]);

                if ($request->hasAny(['city_id', 'address_name', 'latitude', 'longitude'])) {
                    if ($isStandalone) {
                        $location = $event->location;
                        if (! $location) {
                            $location = Location::create([
                                'city_id'      => $request->city_id,
                                'address_name' => $request->address_name,
                                'latitude'     => $request->latitude,
                                'longitude'    => $request->longitude,
                            ]);
                            $event->update(['location_id' => $location->id]);
                        } else {
                            $location->update([
                                'city_id'      => $request->input('city_id', $location->city_id),
                                'address_name' => $request->input('address_name', $location->address_name),
                                'latitude'     => $request->input('latitude', $location->latitude),
                                'longitude'    => $request->input('longitude', $location->longitude),
                            ]);
                        }
                    } else {
                        throw new HttpException(422, __('messages.cannot_update_event_location'));
                    }
                }

                if (! $isStandalone) {
                    $eventDate = $event->event_date;
                    $startTime = $event->start_time;
                    $endTime   = $event->end_time;
                } else {
                    $eventDate = $request->input('event_date', $event->event_date);
                    $startTime = $request->input('start_time', $item->start_time);
                    $endTime   = $request->input('end_time', $item->end_time);

                    if ($request->has('event_date')) {
                        $event->update(['event_date' => $eventDate]);
                    }
                }

                if (! $isCake) {
                    if (! $endTime) {
                        throw new HttpException(422, __('messages.end_time_required'));
                    }

                    if (Carbon::parse($endTime)->lte(Carbon::parse($startTime))) {
                        throw new HttpException(422, __('messages.end_time_after_start_time'));
                    }

                    if (! $isStandalone) {
                        if (
                            Carbon::parse($startTime)->lt(Carbon::parse($event->start_time)) ||
                            Carbon::parse($endTime)->gt(Carbon::parse($event->end_time))
                        ) {
                            throw new HttpException(422, __('messages.service_time_within_event'));
                        }
                    }

                    $isConflict = EventItem::where('provider_service_id', $item->provider_service_id)
                        ->where('id', '!=', $item->id)
                        ->where('event_id', '!=', $event->id)
                        ->whereIn('status', ['pending', 'approved'])
                        ->whereHas('event', function ($query) use ($eventDate) {
                            $query->where('event_date', $eventDate);
                        })
                        ->where(function ($query) use ($startTime, $endTime) {
                            $query->where('start_time', '<', $endTime)
                                ->where('end_time', '>', $startTime);
                        })
                        ->exists();

                    if ($isConflict) {
                        throw new HttpException(409, __('messages.already_booked_other'));
                    }
                } else {
                    if ($isStandalone) {
                        $endTime = null;
                    }
                }

                $newOptions = $request->has('options') ? $request->input('options') : null;
                if ($newOptions && ! empty($newOptions) && $service) {
                    $newOptions = $this->enrichOptionsWithItemNames($newOptions, $service);
                }

                if ($newOptions === null) {
                    $oldOptions = is_string($item->option) ? json_decode($item->option, true) : ($item->option ?? []);
                    if (! empty($oldOptions) && $service) {
                        $oldOptions = $this->enrichOptionsWithItemNames($oldOptions, $service);
                    }
                    $newOptions = $oldOptions;
                }

                $paymentStatus = $item->payment_status;
                if (! empty($newOptions) && is_array($newOptions)) {
                    foreach ($newOptions as $option) {
                        if (! empty($option['paymentMethod'])) {
                            $method = strtolower($option['paymentMethod']);
                            if ($method === 'cash') {
                                $paymentStatus = 'pending_cash';
                            } else {
                                $paymentStatus = 'pending_payment';
                            }
                            break;
                        }
                    }
                }

                if ($request->filled('package_id')) {
                    $package = $service->packages()->where('packages.id', $request->package_id)->first();
                    if (! $package) {
                        throw new HttpException(422, __('messages.package_not_belongs_to_service'));
                    }
                    $totalPrice = (float) $package->price;
                }
                // ✅ التعديل هنا: استخدام الدالة المعدلة calculatePriceFromOptions
                elseif (in_array($service->service->service_type, ['decoration', 'planner'])) {
                    $totalPrice = $this->calculatePriceFromOptions($newOptions, $service);
                    // ✅ إذا كان السعر لا يزال 0، حاول استخراجه من totalPrice في الخيار الأول
                    if ($totalPrice == 0 && ! empty($newOptions[0]['totalPrice'])) {
                        $totalPrice = floatval($newOptions[0]['totalPrice']);
                    }
                } elseif ($isCake) {
                    $totalPrice = (float) $service->price;
                } else {
                    $hours      = Carbon::parse($startTime)->floatDiffInHours(Carbon::parse($endTime));
                    $totalPrice = round($hours * (float) $service->price, 2);
                }

                $currentWithoutThisItem = $event->eventItems()
                    ->where('id', '!=', $item->id)
                    ->whereIn('status', ['pending', 'approved'])
                    ->sum('price');

                $expectedTotal = $currentWithoutThisItem + $totalPrice;

                if ($event->budget && $expectedTotal > $event->budget) {
                    return response()->json([
                        'message'        => __('messages.update_exceeds_budget'),
                        'budget'         => $event->budget,
                        'expected_total' => $expectedTotal,
                    ], 422);
                }

                $updateData = [
                    'price'          => $totalPrice,
                    'start_time'     => $startTime,
                    'end_time'       => $endTime,
                    'payment_status' => $paymentStatus,
                    'status'         => 'pending_modification',
                ];
                if ($request->has('package_id')) {
                    $updateData['package_id'] = $request->package_id;
                }

                if ($newOptions !== null) {
                    $updateData['option'] = $newOptions;
                }

                $item->update($updateData);

                $event->update([
                    'total_amount' => $event->eventItems()->whereIn('status', ['pending', 'approved'])->sum('price'),
                ]);

                return [
                    'total_price' => $totalPrice,
                    'item'        => $item->fresh()->load(['event', 'providerService.service']),
                ];
            });

            return response()->json([
                'message'     => __('messages.booking_updated_success'),
                'total_price' => $response['total_price'],
                'item'        => $response['item'],
            ], 200);

        } catch (\Throwable $e) {
            $status = $e instanceof HttpException ? $e->getStatusCode() : 500;
            return response()->json([
                'message' => $status == 500 ? __('messages.booking_update_error') : $e->getMessage(),
                'error'   => $e->getMessage(),
            ], $status);
        }
    }

    public function cancelBooking(EventItem $booking, Request $request)
    {
        try {
            return DB::transaction(function () use ($booking, $request) {

                $booking->load(['event', 'providerService']);

                if ($booking->event->user_id != Auth::id()) {
                    throw new \Exception(__('messages.unauthorized_cancellation'));
                }

                if (in_array($booking->status, ['cancelled', 'rejected'])) {
                    throw new \Exception(__('messages.cannot_cancel_current_status', ['status' => $booking->status]));
                }

                $eventDate = Carbon::parse($booking->event->event_date);
                $hoursLeft = Carbon::now()->diffInHours($eventDate, false);

                if ($hoursLeft < 24) {
                    $penaltyRate = 1.00;
                } elseif ($hoursLeft < 48) {
                    $penaltyRate = 0.50;
                } else {
                    $penaltyRate = 0.00;
                }

                $booking->update([
                    'status'              => 'cancelled',
                    'cancelled_at'        => now(),
                    'cancellation_reason' => $request->reason ?? __('messages.default_cancellation_reason'),
                ]);

                if ($booking->payment_status === 'paid') {
                    $paymentController = app(\App\Http\Controllers\PaymentController::class);
                    $refundResponse    = $paymentController->refund($booking, $penaltyRate);

                    if ($refundResponse->getStatusCode() != 200) {
                        throw new \Exception(json_decode($refundResponse->getContent(), true)['message'] ?? __('messages.refund_failed'));
                    }

                    $booking->update(['price' => round($booking->price * $penaltyRate, 2)]);
                } else {
                    $booking->update([
                        'payment_status' => 'cancelled',
                        'price'          => 0,
                    ]);
                }

                $event    = $booking->event->fresh();
                $newTotal = round($event->eventItems()->whereNotIn('status', ['cancelled', 'rejected'])->sum('price'), 2);

                $event->update([
                    'total_amount' => $newTotal,
                    'status'       => $newTotal == 0 ? 'cancelled' : 'pending',
                ]);

                if ($penaltyRate == 1.00) {
                    $message = __('messages.cancellation_success_penalty_100');
                } elseif ($penaltyRate == 0.50) {
                    $message = __('messages.cancellation_success_penalty_50');
                } else {
                    $message = __('messages.cancellation_success_full_refund');
                }

                return response()->json([
                    'message'      => $message,
                    'penalty_rate' => $penaltyRate,
                    'event_total'  => $event->total_amount,
                ], 200);
            });

        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('messages.error_cancelling_booking'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function showEvent($id)
    {
        try {
            $event = Event::with([
                'location.city',
                'eventItems.providerService.service',
                'eventItems.providerService.images',
                'eventItems.providerService.location',
            ])
                ->where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (! $event) {
                return response()->json(['message' => __('messages.event_not_found')], 404);
            }

            return response()->json([
                'message' => __('messages.event_details_fetched_success'),
                'event'   => [
                    'id'           => $event->id,
                    'name'         => $event->name,
                    'event_date'   => $event->event_date,
                    'start_time'   => $event->start_time,
                    'end_time'     => $event->end_time,
                    'budget'       => $event->budget,
                    'total_amount' => $event->total_amount,
                    'status'       => $event->status,
                    'location'     => $event->location->address_name ?? null,
                    'city'         => $event->location->city->name ?? null,
                    'bookings'     => $event->eventItems->map(function ($item) {
                        return [
                            'id'           => $item->id,
                            'service_name' => $item->providerService->service->name ?? __('messages.unknown_service'),
                            'price'        => $item->price,
                            'status'       => $item->status,
                            'start_time'   => $item->start_time,
                            'end_time'     => $item->end_time,
                            'quantity'     => $item->quantity,
                            'options'      => $item->option ?? [],
                            'image'        => $item->providerService->images->isNotEmpty()
                                ? asset('storage/' . $item->providerService->images->first()->image_path)
                                : null,
                        ];
                    })
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('messages.error_fetching_details'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function myEvents()
    {
        try {
            $events = Event::with(['location.city', 'eventItems.providerService.service'])
                ->where('user_id', Auth::id())
                ->orderBy('event_date', 'desc')
                ->get();

            return response()->json([
                'message' => __('messages.events_fetched_success'),
                'events'  => $events->map(function ($event) {
                    return [
                        'id'           => $event->id,
                        'name'         => $event->name,
                        'type'         => $event->type,
                        'status'       => $event->status,
                        'event_date'   => $event->event_date,
                        'start_time'   => $event->start_time,
                        'end_time'     => $event->end_time,
                        'budget'       => $event->budget,
                        'total_amount' => $event->total_amount,
                        'city'         => $event->location->city->name ?? null,
                        'bookings'     => $event->eventItems->map(function ($item) {
                            return [
                                'id'           => $item->id,
                                'service_name' => $item->providerService->service->name ?? __('messages.unknown_service'),
                                'price'        => $item->price,
                                'status'       => $item->status,
                                'start_time'   => $item->start_time,
                                'end_time'     => $item->end_time,
                                'quantity'     => $item->quantity,
                                'options'      => $item->option ?? [],
                                'image'        => $item->providerService->images->first()
                                    ? asset('storage/' . $item->providerService->images->first()->image_path)
                                    : null,
                            ];
                        })
                    ];
                })
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => __('messages.error_occurred'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ دالة myBookings المعدلة (ترسل status و event_date و event.type و service_type)
     * ✅ تم إضافة qr_token في الاستجابة
     */
    public function myBookings(Request $request)
    {
        $userId = $request->user()->id;

        $bookings = EventItem::with([
            'event',
            'event.location',
            'providerService.provider',
            'providerService.service',
            'providerService.images',
            'providerService.location',
        ])
            ->whereHas('event', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->get();

        $today = Carbon::today();

        $cancelledAndRejected = $bookings->filter(function ($item) {
            $status = trim($item->status);
            return $status === 'cancelled' || $status === 'rejected';
        })->values();

        $currentAndUpcoming = $bookings->filter(function ($item) use ($today) {
            $eventDate = Carbon::parse($item->event->event_date);
            return $eventDate->gte($today) && ! in_array(trim($item->status), ['cancelled', 'rejected']);
        })->values();

        $completedPast = $bookings->filter(function ($item) use ($today) {
            $eventDate = Carbon::parse($item->event->event_date);
            return $eventDate->lt($today) && ! in_array(trim($item->status), ['cancelled', 'rejected']);
        })->values();

        $formatItems = function ($items) {
            return $items->map(function ($item) {
                return [
                    'id'                  => $item->id,
                    'event_id'            => $item->event_id,
                    'provider_service_id' => $item->provider_service_id,
                    'package_id'          => $item->package_id,
                    'price'               => $item->price,
                    'payment_status'      => $item->payment_status,
                    'status'              => $item->status,
                    'qr_token'            => $item->qr_token,
                    'start_time'          => $item->start_time,
                    'end_time'            => $item->end_time,
                    'option'              => $item->option,
                    'created_at'          => $item->created_at,
                    'updated_at'          => $item->updated_at,
                    'event'               => $item->event ? [
                        'id'         => $item->event->id,
                        'name'       => $item->event->name,
                        'event_date' => $item->event->event_date,
                        'status'     => $item->event->status,
                        'type'       => $item->event->type,
                    ] : null,
                 'provider_service' => $item->providerService ? [
    'id' => $item->providerService->id,

    'name' => $item->providerService->name,

    'provider' => $item->providerService->provider ? [
        'id' => $item->providerService->provider->id,
        'name' => $item->providerService->provider->name
            ?? $item->providerService->provider->username,
    ] : null,

    'service' => $item->providerService->service ? [
        'id' => $item->providerService->service->id,
        'name' => $item->providerService->service->name,
        'service_type' => $item->providerService->service->service_type,
    ] : null,

    'images' => $item->providerService->images->map(function ($img) {
        return asset('storage/' . $img->image_path);
    })->values()->toArray(),

] : null,
                ];
            })->values();
        };

        return response()->json([
            'upcoming'  => $formatItems($currentAndUpcoming),
            'completed' => $formatItems($completedPast),
            'cancelled' => $formatItems($cancelledAndRejected),
        ], 200);
    }

    public function cancelWholeEvent($id, Request $request)
    {
        DB::beginTransaction();

        try {
            $event = Event::with('eventItems')->findOrFail($id);

            if ($event->user_id != Auth::id()) {
                return response()->json(['message' => __('messages.unauthorized_cancel_event')], 403);
            }

            if ($event->status == 'cancelled') {
                return response()->json(['message' => __('messages.event_already_cancelled')], 422);
            }

            if ($event->status == 'completed') {
                return response()->json(['message' => __('messages.cannot_cancel_completed_event')], 422);
            }

            $eventDate = Carbon::parse($event->event_date);
            $hoursLeft = Carbon::now()->diffInHours($eventDate, false);

            if ($hoursLeft < 24) {
                $penaltyRate = 1.00;
            } elseif ($hoursLeft >= 24 && $hoursLeft < 48) {
                $penaltyRate = 0.50;
            } else {
                $penaltyRate = 0.00;
            }

            $paymentController = app(\App\Http\Controllers\PaymentController::class);

            foreach ($event->eventItems as $item) {
                if (in_array($item->status, ['cancelled', 'rejected'])) {
                    continue;
                }

                $item->update([
                    'status'              => 'cancelled',
                    'cancelled_at'        => now(),
                    'cancellation_reason' => $request->reason ?? __('messages.cancel_event_reason'),
                ]);

                if ($item->payment_status == 'paid') {
                    $refundResponse = $paymentController->refund($item, $penaltyRate);
                    if ($refundResponse->getStatusCode() !== 200) {
                        DB::rollBack();
                        return $refundResponse;
                    }
                    $item->update(['price' => round($item->price * $penaltyRate, 2)]);
                } else {
                    $item->update([
                        'payment_status' => 'cancelled',
                        'price'          => 0,
                    ]);
                }
            }

            $event->update([
                'status'       => 'cancelled',
                'total_amount' => 0.00,
            ]);

            DB::commit();

            if ($penaltyRate == 1.00) {
                $message = __('messages.event_cancellation_success_penalty_100');
            } elseif ($penaltyRate == 0.50) {
                $message = __('messages.event_cancellation_success_penalty_50');
            } else {
                $message = __('messages.event_cancellation_success_full_refund');
            }

            return response()->json(['message' => $message]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => __('messages.error_cancelling_event'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function filterServices(Request $request)
    {
        $query = ProviderService::query()
            ->with([
                'service',
                'location.city',
                'images',
                'user',
            ]);

        if ($request->filled('north') &&
            $request->filled('south') &&
            $request->filled('east') &&
            $request->filled('west')) {

            $query->whereHas('location', function ($q) use ($request) {
                $q->whereBetween('latitude', [$request->south, $request->north])
                    ->whereBetween('longitude', [$request->west, $request->east]);
            });
        } elseif ($request->filled('latitude') &&
            $request->filled('longitude') &&
            $request->filled('radius')) {

            $lat    = (float) $request->latitude;
            $lng    = (float) $request->longitude;
            $radius = (float) $request->radius;

            $query->join('locations', 'provider_services.location_id', '=', 'locations.id')
                ->whereRaw("
                (
                    6371 * acos(
                        LEAST(1.0,
                            cos(radians(?)) *
                            cos(radians(locations.latitude)) *
                            cos(radians(locations.longitude) - radians(?)) +
                            sin(radians(?)) *
                            sin(radians(locations.latitude))
                        )
                    )
                ) <= ?
            ", [$lat, $lng, $lat, $radius])
                ->orderByRaw("
                6371 * acos(
                    LEAST(1.0,
                        cos(radians(?)) *
                        cos(radians(locations.latitude)) *
                        cos(radians(locations.longitude) - radians(?)) +
                        sin(radians(?)) *
                        sin(radians(locations.latitude))
                    )
                ) ASC
            ", [$lat, $lng, $lat])
                ->select('provider_services.*');
        } elseif ($request->filled('city_id')) {
            $query->whereHas('location', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        $query->where('provider_services.status', 'active');

        $serviceType = $request->input('category') ?? $request->input('service_type');

        if ($serviceType) {
            $normalizedType = rtrim($serviceType, 's');
            $query->whereHas('service', function ($q) use ($normalizedType) {
                $q->where('service_type', $normalizedType);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('provider_services.description', 'LIKE', "%{$search}%")
                    ->orWhereHas('service', function ($subQ) use ($search) {
                        $subQ->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('min_price')) {
            $query->where('provider_services.price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('provider_services.price', '<=', $request->max_price);
        }

        if ($request->filled('capacity')) {
            $query->where('provider_services.capacity', '>=', $request->capacity);
        }

        if ($request->filled('music_type')) {
            $query->where('provider_services.music_type', $request->music_type);
        }

        if (! $request->filled('latitude') || ! $request->filled('longitude') || ! $request->filled('radius')) {
            $query->orderBy('provider_services.created_at', 'desc');
        }

        return response()->json([
            'services' => $query->get(),
        ]);
    }

    public function index()
    {
        $cities = City::select('id', 'name')->get();
        return response()->json($cities);
    }

    public function generate($id)
    {
        $guest = Invitation::findOrFail($id);
        $url   = url('/api/verify-guest/' . $guest->code);
        return QrCode::size(300)->generate($url);
    }

    public function verifyGuest($code)
    {
        $guest = Invitation::where('code', $code)->first();

        if (! $guest) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.barcode_not_found'),
            ], 404);
        }

        if ($guest->is_scanned) {
            return response()->json([
                'status'     => 'warning',
                'message'    => __('messages.invitation_already_scanned', ['name' => $guest->guest_name]),
                'scanned_at' => $guest->scanned_at,
            ], 409);
        }

        $guest->update([
            'is_scanned' => true,
            'scanned_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => __('messages.welcome_guest', ['name' => $guest->guest_name]),
        ], 200);
    }

    public function index1()
    {
        $guests = Invitation::all();
        return response()->json([
            'data' => $guests,
        ]);
    }

    public function indexServices(Request $request)
    {
        $query = ProviderService::query()
            ->with(['service', 'location.city', 'images', 'user'])
            ->where('status', 'active');

        if ($request->has('category')) {
            $category = rtrim($request->category, 's');
            $query->whereHas('service', function ($q) use ($category) {
                $q->where('service_type', $category);
            });
        }

        return response()->json([
            'status' => true,
            'data'   => $query->latest()->paginate(10),
        ]);
    }

    public function showService($id)
    {
        $service = ProviderService::with(['service', 'location.city', 'images', 'packages', 'user'])
            ->find($id);

        if (! $service) {
            return response()->json([
                'status'  => false,
                'message' => 'الخدمة غير موجودة',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $service,
        ]);
    }

//تبعات الموقع
   public function getBookedSlots($providerServiceId)
{
    $slots = EventItem::join('events', 'events.id', '=', 'event_items.event_id')
        ->where('event_items.provider_service_id', $providerServiceId)
        ->whereNotIn('event_items.status', ['cancelled', 'rejected'])
        ->whereNotIn('event_items.payment_status', ['refunded', 'expired'])
        ->whereDate('events.event_date', '>=', now()->toDateString())
        ->select('events.event_date as date', 'event_items.start_time', 'event_items.end_time')
        ->get();

    return response()->json(['slots' => $slots]);
}
    public function servicesByProvider()
    {
        $provider = User::where('id', auth()->id())
            ->where('role', 'provider')
            ->with([
                'providerServices' => function ($q) {
                    $q->where('status', 'active');
                },
                'providerServices.service',
                'providerServices.location.city',
                'providerServices.images',
            ])
            ->first();

        $provider->makeHidden(['email_verified_at', 'deleted_at', 'google_id', 'updated_at', 'created_at']);
        $provider->providerServices->each->makeHidden(['updated_at', 'created_at', 'location_id', 'service_id']);

        return response()->json([
            'data' => $provider,
        ]);
    }
    public function generateForEventItem($eventItemId)
    {
        $eventItem = EventItem::with('event.user')->findOrFail($eventItemId);

        // إذا في دعوة مرتبطة بهالحجز مسبقاً منستخدمها، إذا لأ منعمل وحدة جديدة
        $invitation = Invitation::firstOrCreate(
            ['event_item_id' => $eventItem->id],
            [
                'guest_name' => $eventItem->event->user->username ?? 'ضيف',
                'code'       => (string) Str::uuid(),
            ]
        );

        $url = url('/api/verify-guest/' . $invitation->code);

        return QrCode::size(300)->generate($url);
    }
// ============================================================
// 👈 جديد: حجوزات المزود (قادمة / منتهية / ملغاة) — من منظور المزود
// ============================================================
    public function providerBookings(Request $request)
    {

        $providerId = Auth::id();

        $bookings = EventItem::with(['event.user', 'event.location.city', 'providerService.service', 'providerService.images'])
            ->whereHas('providerService', function ($q) use ($providerId) {
                $q->where('user_id', $providerId);
            })
            ->get();

        $today = Carbon::today();

        // أ) القادمة والحالية: مقبولة أو معلقة، وتاريخها لسا ما إجا
        $upcoming = $bookings->filter(function ($item) use ($today) {
            $status    = trim($item->status);
            $eventDate = Carbon::parse($item->event->event_date);
            return in_array($status, ['pending', 'approved', 'pending_modification'])
            && $eventDate->gte($today);
        })->values();

        // ب) المنتهية: مقبولة سابقاً وتاريخها فات
        $completed = $bookings->filter(function ($item) use ($today) {
            $status    = trim($item->status);
            $eventDate = Carbon::parse($item->event->event_date);
            return in_array($status, ['approved', 'completed'])
            && $eventDate->lt($today);
        })->values();

        // ج) الملغاة أو المرفوضة
        $cancelled = $bookings->filter(function ($item) {
            return in_array(trim($item->status), ['cancelled', 'rejected']);
        })->values();

        $formatItem = function ($item) {
            return [
                'id'             => $item->id,
                'status'         => $item->status,
                'payment_status' => $item->payment_status,
                'price'          => $item->price,
                'start_time'     => $item->start_time,
                'end_time'       => $item->end_time,
                'service_name'   => $item->providerService->service->name ?? 'غير معروف',
                'image'          => $item->providerService->images->first()
                    ? asset('storage/' . $item->providerService->images->first()->image_path)
                    : null,
                'customer_name'  => $item->event->user->username ?? 'عميل',
                'customer_phone' => $item->event->user->phone ?? null,
                'event_date'     => $item->event->event_date,
                'city'           => $item->event->location->city->name ?? null,
            ];
        };

        return response()->json([
            'upcoming'  => $upcoming->map($formatItem)->values(),
            'completed' => $completed->map($formatItem)->values(),
            'cancelled' => $cancelled->map($formatItem)->values(),
        ], 200);
    }

}
