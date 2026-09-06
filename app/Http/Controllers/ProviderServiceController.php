<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Location;
use App\Models\ProviderService;
use App\Models\ServiceImage;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use App\Models\Package;
use App\Models\Feature;



class ProviderServiceController extends Controller
{
    /**
     * لوحة تحكم المزود الذكية
     */
    public function index(Request $request)
    {
        try {
            // 1. تجهيز الاستعلام الأساسي مع العلاقات والخدمات "النشطة" فقط
            $query = ProviderService::with(['service', 'location.city', 'images', 'user', 'packages'])
                ->where('status', 'active') // 🌟 لمنع ظهور الخدمات المحذوفة
                ->latest();

            // 2. الفلترة حسب نوع الخدمة باستخدام when و whereHas
            $query->when($request->service_type, function ($q) use ($request) {
                $q->whereHas('service', function ($serviceQuery) use ($request) {
                    $serviceQuery->where('service_type', $request->service_type);
                });
            });
            // 3. تنفيذ الاستعلام مع الـ Pagination
            $allServices = $query->paginate(20);

            return response()->json([
                'status'  => true,
                'message' => ('messages.all_services_fetched_success'), // 🌟 تم إضافة  للترجمة
                'data'    => $allServices
            ], 200);
        } catch (\Exception $e) {
            Log::error('[ALL SERVICES INDEX ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => ('messages.general_error') // 🌟 تم إضافة  للترجمة
            ], 500);
        }
    }
    public function show($id)
    {
        try {
            $service = ProviderService::with(['service', 'location.city', 'images', 'packages.images','videos',
            'packages.features'])
                ->find($id);
            if (!$service) {
                return response()->json([
                    'status'  => false,
                    'message' => ('messages.service_not_found')
                ], 404);
            }
            if (is_string($service->description)) {
                $service->description = json_decode($service->description);
            }
            // حساب التذاكر المتبقية للفعاليات العامة فقط
            if ($service->service && $service->service->service_type === 'public_event') {
                $features = json_decode($service->features, true) ?? [];
                $totalTickets = isset($features['ticket_count']) ? (int)$features['ticket_count'] : 0;

                $bookedTickets = DB::table('event_items')
                    ->where('provider_service_id', $service->id)
                    ->sum('quantity');

                $service->available_tickets = max(0, $totalTickets - $bookedTickets);
            }
            return response()->json([
                'status'  => true,
                'message' => ('messages.service_details_fetched_success'),
                'data'    => $service
            ], 200);
        } catch (\Exception $e) {
            Log::error('[SHOW PROVIDER SERVICE DETAILS ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => ('messages.error_fetching_service_details')
            ], 500);
        }
    }
    public function store(Request $request)
    {
        $providerId = Auth::id();
        $serviceType = $request->input('service_type');
          //منع تكرار الخدمة
            $existingService = ProviderService::where('user_id', $providerId)
        ->where('name', $request->input('name'))
        ->whereHas('location', function ($query) use ($request) {
            $query->where('city_id', $request->input('city_id'))
                ->where('address_name', $request->input('address_name'));
        })
        ->exists();

    if ($existingService) {
        return response()->json([
            'status'  => false,
            'message' => 'عذراً، هذه الخدمة بنفس العنوان مضافة لديك مسبقاً.',
        ], 422);
    }

        // 1. التحقق من البيانات المشتركة
        $commonRules = [
            'name'         => 'required|string|max:255',
            'service_type' => 'required|in:decoration,cake,hall,photographer,music,public_event',
            'price'        => 'nullable|numeric|min:0',
            'city_id'      => 'required|exists:cities,id',
            'address_name' => 'required|string|max:500',
            'latitude'     => 'required|numeric',
            'longitude'    => 'required|numeric',
            'video'        => 'nullable|file|mimes:mp4,mov,ogg,qt|max:20480',

            // التحقق من مصفوفة الباقات العامة
            'packages'                  => 'nullable|array',
            'packages.*.name'           => 'required_with:packages|string|max:255',
            'packages.*.price'          => 'required_with:packages|numeric|min:0',
            'packages.*.description'    => 'nullable|string',
            'packages.*.feature_ids'    => 'nullable|array',
            'packages.*.feature_ids.*'  => 'integer|exists:features,id',

            // [خاص بالمنسق والكيك]: مؤشرات الصور المربوطة بالباقة
            'packages.*.image_indices'   => 'nullable|array',
            'packages.*.image_indices.*' => 'integer',

            // 🌟 [خاص بالمنسق فقط]: معرفات الصور الموجودة مسبقاً في قاعدة البيانات
            'packages.*.image_ids'       => 'nullable|array',
            'packages.*.image_ids.*'     => 'integer|exists:service_images,id',
            // 📸 [خاص بالمصور]: المواصفات المطلوبة للباقة
            'packages.*.photos_count'   => 'nullable|integer|min:0',
            'packages.*.video_duration' => 'nullable|string|max:255',
            'packages.*.video_quality'  => 'nullable|string|max:255',
        ];

        // 2. شروط التحقق الخاصة بالصور
        $imageRules = [];
        if (in_array($serviceType, ['decoration', 'cake'])) {
            $imageRules = [
                'images'         => 'required|array|min:1|max:10',
                'images.*.file'  => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'images.*.price' => 'required|numeric|min:0',
                'images.*.title' => 'required|string|max:255',
                'previous_work_images'   => 'nullable|array|max:10',
                'previous_work_images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            ];
        } else {
            $imageRules = [
                'images'   => 'nullable|array|min:1|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            ];
        }
        $specificRules = $this->getSpecificValidationRules($serviceType);
        $validatedData = $request->validate(array_merge($commonRules, $specificRules, $imageRules));
        DB::beginTransaction();
        try {
            // أ) إنشاء الموقع
            $location = Location::create([
                'city_id'      => $validatedData['city_id'],
                'address_name' => $validatedData['address_name'],
                'latitude'     => $validatedData['latitude'],
                'longitude'    => $validatedData['longitude'],
            ]);

            // ب) جلب أو إنشاء الخدمة الأساسية
            $baseService = Service::firstOrCreate(
                ['service_type' => $validatedData['service_type']],
                ['name' => $validatedData['name']]
            );
            // ج) جلب التفاصيل الخاصة بنوع الخدمة
            $specificDetails = $request->only(array_keys($specificRules));
            // 🌟 التعديل الأساسي هنا: استخراج الوصف النصي المباشر إذا كان موجوداً (مثل خدمة الكيك)
            $plainDescription = $specificDetails['description'] ?? null;

            // إزالة الوصف من مصفوفة التفاصيل الخاصة حتى لا يتكرر داخل حقل الـ JSON (features)
            unset($specificDetails['description']);
            $finalPrice = $validatedData['price'] ?? 0;
            if (isset($validatedData['price_per_hour'])) {
                $finalPrice = $validatedData['price_per_hour'];
            } elseif (isset($validatedData['ticket_price'])) {
                $finalPrice = $validatedData['ticket_price'];
            }

            $videoPath = null;
            if ($request->hasFile('video')) {
                $videoPath = $request->file('video')->store('services/videos', 'public');
            }
            // هـ) إنشاء السجل الأساسي للمزود
            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'service_id'  => $baseService->id,
                'location_id' => $location->id,
                'name'        => $validatedData['name'], // 🌟 هذا هو السطر الناقص يا رغد!
                'price'       => $finalPrice,
                'description' => $plainDescription, // 🌟 أصبح يستقبل النص العادي مباشرة
                'features'    => json_encode($specificDetails, JSON_UNESCAPED_UNICODE), // 🌟 تم تحويل التخزين إلى حقل features
                'video_path'  => $videoPath,
                'status'      => 'active',
            ]);

            $savedImagesMap = [];
            // 4. رفع الصور وحفظها
            // 4. رفع الصور وحفظها
            if (in_array($serviceType, ['decoration', 'cake'])) {

                // أ) حفظ صور الأعمال السابقة (بدون سعر)
                if ($request->hasFile('previous_work_images')) {
                    foreach ($request->file('previous_work_images') as $imageFile) {
                        $path = $imageFile->store('services/images', 'public');
                        ServiceImage::create([
                            'provider_service_id' => $providerService->id,
                            'image_path'          => $path,
                            'price'               => null,
                            'title'               => 'أعمال سابقة'
                        ]);
                    }
                }

                // ب) حفظ صور المخزون (الكوشة والزينة - تحتاج السعر والعنوان)
                if ($request->has('images')) {
                    foreach ($request->file('images') as $index => $imageGroup) {
                        if (isset($imageGroup['file'])) {
                            $path = $imageGroup['file']->store('services/images', 'public');
                            $imagePrice = $validatedData['images'][$index]['price'];
                            $imageTitle = $validatedData['images'][$index]['title'];

                            $serviceImage = ServiceImage::create([
                                'provider_service_id' => $providerService->id,
                                'image_path'          => $path,
                                'price'               => $imagePrice,
                                'title'               => $imageTitle
                            ]);
                            $savedImagesMap[$index] = $serviceImage->id;
                        }
                    }
                }
            } else {
                // ج) حفظ صور باقي الخدمات (التي لا تحتاج أسعار مثل المصور وغيره)
                if ($request->has('images')) {
                    foreach ($request->file('images') as $imageFile) {
                        $path = $imageFile->store('services/images', 'public');
                        ServiceImage::create([
                            'provider_service_id' => $providerService->id,
                            'image_path'          => $path,
                            'price'               => null
                        ]);
                    }
                }
            }
            // 5. إنشاء الباقات المرنة للخدمات
            if ($request->has('packages')) {
                foreach ($validatedData['packages'] as $packageData) {

                    $packageCustomDescription = null;
                    if ($serviceType === 'photographer') {
                        $photographerSpecs = [
                            'photos_count'   => $packageData['photos_count'] ?? 0,
                            'video_duration' => $packageData['video_duration'] ?? null,
                            'video_quality'  => $packageData['video_quality'] ?? null,
                        ];
                        $packageCustomDescription = json_encode($photographerSpecs, JSON_UNESCAPED_UNICODE);
                    } elseif ($serviceType === 'cake') {
                        $packageCustomDescription = $packageData['description'] ?? null;
                    } elseif ($serviceType === 'hall') {
                        $packageCustomDescription = $packageData['description'] ?? null; // 🌟 هذا هو السطر المضاف فقط لحل مشكلة الصالات
                    } elseif ($serviceType === 'decoration') {
                        $packageCustomDescription = $packageData['description'] ?? null; // 🌟 إضافة وصف باقة المنسق
                    }
                    // إنشاء سجل الباقة
                    $package = Package::create([
                        'user_id'     => $providerId,
                        'name'        => $packageData['name'],
                        'price'       => $packageData['price'],
                        'description' => $packageCustomDescription,
                        'status'      => 'active'
                    ]);

                    // ربط الباقة بالخدمة الحالية
                    DB::table('package_services')->insert([
                        'provider_service_id' => $providerService->id,
                        'package_id'          => $package->id,
                        'created_at'          => now(),
                        'updated_at'          => now()
                    ]);
                    // ربط الصور المحددة بالباقة
                    if (in_array($serviceType, ['decoration', 'cake']) && !empty($packageData['image_indices'])) {
                        foreach ($packageData['image_indices'] as $imgIndex) {
                            if (isset($savedImagesMap[$imgIndex])) {
                                DB::table('package_images')->insert([
                                    'package_id'       => $package->id,
                                    'service_image_id' => $savedImagesMap[$imgIndex],
                                    'created_at'       => now(),
                                    'updated_at'       => now()
                                ]);
                            }
                        }
                    }
                    if ($serviceType === 'decoration' && !empty($packageData['image_ids'])) {
                        foreach ($packageData['image_ids'] as $existingImageId) {
                            DB::table('package_images')->insert([
                                'package_id'       => $package->id,
                                'service_image_id' => $existingImageId,
                                'created_at'       => now(),
                                'updated_at'       => now()
                            ]);
                        }
                    }
                    // ربط الميزات إن وُجدت
                    if (!empty($packageData['feature_ids'])) {
                        foreach ($packageData['feature_ids'] as $featureId) {
                            DB::table('package_features')->insert([
                                'package_id' => $package->id,
                                'feature_id' => $featureId,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                }
            }
            DB::commit();
            // استخدام الترجمة في رسالة النجاح
            return response()->json([
                'status'  => true,
                'message' => ('messages.service_stored_success'),
                'data' => $providerService->load(['service', 'images', 'packages.images'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[STORE PROVIDER SERVICE ERROR] ' . $e->getMessage());

            // استخدام الترجمة في رسالة الخطأ
            return response()->json([
                'status'  => false,
                'message' => ('messages.error_storing_service')
            ], 500);
        }
    }
    private function getSpecificValidationRules($type)
    {
        switch ($type) {
            case 'decoration':
                return [
                    'working_days'  => 'required|string',
                    'working_hours' => 'required|string',
                ];
            case 'cake':
                return [
                    'working_days'  => 'required|string',
                    'working_hours' => 'required|string',
                    'colors'        => 'required|array',
                    'filling'       => 'required|array',
                    'flavor'        => 'required|array',
                    'quantity'      => 'required|integer|min:1',
                    'description'   => 'required|string',
                    'delivery_type' => 'required|in:delivery,pickup,both',
                ];

            case 'hall':
                return [
                    'event_types'   => 'required|array',
                    'capacity'      => 'required|integer|min:1',
                    'price_per_hour' => 'required|numeric|min:0',
                    'has_facilities' => 'required|boolean',
                    'facilities'    => 'nullable|array',
                ];

            case 'photographer':
                return [
                    'working_days'  => 'required|string',
                    'working_hours' => 'required|string',
                    'price_per_hour' => 'required|numeric|min:0',
                    'processing_time' => 'required|string',
                ];

            case 'music':
                return [
                    'music_types'   => 'required|array',
                    'music_types.*' => 'required|in:arada,nasheed,dj',
                    'band_type'     => 'required|in:male,female,mixed',
                    'price_per_hour' => 'required|numeric|min:0',
                ];

            case 'public_event':
                return [
                    'start_time'    => 'required|date_format:H:i',
                    'end_time'      => 'required|date_format:H:i',
                    'event_date'    => 'required|date|after_or_equal:today',
                    'is_free'       => 'required|boolean',
                    'ticket_price'  => 'required_if:is_free,false|nullable|numeric|min:0',
                    'ticket_count'  => 'required_if:is_free,false|nullable|integer|min:1',
                ];

            default:
                return [];
        }
    }
    public function update(Request $request, $id)
    {
        $providerId = Auth::id();
        // 1. البحث عن الخدمة والتأكد من الصلاحية
        $providerService = ProviderService::with('service')->where('id', $id)
            ->where('user_id', $providerId)
            ->first();

        if (!$providerService) {
            return response()->json([
                'status'  => false,
                'message' => ('messages.service_not_found_or_unauthorized')
            ], 403);
        }
        // جلب نوع الخدمة من قاعدة البيانات لضمان الدقة
        $serviceType = $providerService->service->service_type;
        // 2. شروط التحقق المشتركة (مضاف إليها تفاصيل الباقات والصور كما في store)
        $commonRules = [
            'name'         => 'nullable|string|max:255',
            'price'        => 'nullable|numeric|min:0',
            'city_id'      => 'nullable|exists:cities,id',
            'address_name' => 'nullable|string|max:500',
            'latitude'     => 'nullable|numeric',
            'longitude'    => 'nullable|numeric',
            'video'        => 'nullable|file|mimes:mp4,mov,ogg,qt|max:20480',
            'packages'                  => 'nullable|array',
            'packages.*.name'           => 'required_with:packages|string|max:255',
            'packages.*.price'          => 'required_with:packages|numeric|min:0',
            'packages.*.description'    => 'nullable|string',
            'packages.*.feature_ids'    => 'nullable|array',
            'packages.*.feature_ids.*'  => 'integer|exists:features,id',
            // [خاص بالمنسق والكيك]: مؤشرات الصور
            'packages.*.image_indices'   => 'nullable|array',
            'packages.*.image_indices.*' => 'integer',
            // 🌟 [إضافة جديدة - خاص بالمنسق فقط]: معرفات الصور الموجودة مسبقاً في قاعدة البيانات
            'packages.*.image_ids'       => 'nullable|array',
            'packages.*.image_ids.*'     => 'integer|exists:service_images,id',
            // [خاص بالمصور]: المواصفات
            'packages.*.photos_count'   => 'nullable|integer|min:0',
            'packages.*.video_duration' => 'nullable|string|max:255',
            'packages.*.video_quality'  => 'nullable|string|max:255',
        ];
        // شروط التحقق الخاصة بالصور (حسب نوع الخدمة)
        $imageRules = [];
        if (in_array($serviceType, ['decoration', 'cake'])) {
            $imageRules = [
                'previous_work_images'   => 'nullable|array|max:10',
                'previous_work_images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
                'images'         => 'nullable|array|max:10',
                'images.*.file'  => 'required_with:images|image|mimes:jpeg,png,jpg|max:2048',
                'images.*.price' => 'required_with:images|numeric|min:0',
                'images.*.title' => 'required_with:images|string|max:255',
            ];
        } else {
            $imageRules = [
                'images'   => 'nullable|array|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            ];
        }
        // 3. جلب الشروط الخاصة وتحويل الـ required إلى nullable تلقائياً للتعديل
        // $specificRules = $this->getSpecificValidationRules($serviceType);
        // foreach ($specificRules as $key => $value) {
        //     $specificRules[$key] = str_replace('required', 'nullable', $value);
        // }احكي لرغددد

        // ✅ استخدمي هذا بدلاً منه
        $specificRules = $this->getSpecificValidationRules($serviceType);
        // تشغيل التحقق بأمان
        $validatedData = $request->validate(array_merge($commonRules, $specificRules, $imageRules));
        DB::beginTransaction();
        try {
            // أ) تحديث الموقع ذكياً
            $location = Location::find($providerService->location_id);
            if ($location) {
                $location->update([
                    'city_id'      => $request->input('city_id', $location->city_id),
                    'address_name' => $request->input('address_name', $location->address_name),
                    'latitude'     => $request->input('latitude', $location->latitude),
                    'longitude'    => $request->input('longitude', $location->longitude),
                ]);
            }
            // ب) تحديث الاسم في جدول الخدمات الأساسي
            if ($request->filled('name')) {
                $providerService->service()->update(['name' => $request->input('name')]);
            }
            // ج) 🌟 دمج تفاصيل الـ JSON الذكي (التعديل الأهم: القراءة من features)
            $oldFeatures = json_decode($providerService->features, true) ?? [];
            $newDetails = $request->only(array_keys($specificRules));
            // استخراج الوصف النصي إن تم إرساله، وإلا نحتفظ بالقديم
            $plainDescription = $request->input('description', $providerService->description);
            unset($newDetails['description']); // إزالته من الـ JSON
            $specificDetails = array_merge($oldFeatures, array_filter($newDetails, function ($value) {
                return $value !== null;
            }));
            // د) إعادة حساب السعر
            $finalPrice = $providerService->price;
            if ($request->has('price')) {
                $finalPrice = $request->input('price');
            } elseif ($request->has('price_per_hour')) {
                $finalPrice = $request->input('price_per_hour');
            } elseif ($request->has('ticket_price')) {
                $finalPrice = $request->input('ticket_price');
            }
            // هـ) معالجة الفيديو
            $videoPath = $providerService->video_path;
            if ($request->hasFile('video')) {
                if ($videoPath && \Storage::disk('public')->exists($videoPath)) {
                    \Storage::disk('public')->delete($videoPath);
                }
                $videoPath = $request->file('video')->store('services/videos', 'public');
            }
            // و) تحديث سجل الـ ProviderService الأساسي 🌟
            $providerService->update([
                'price'       => $finalPrice,
                'description' => $plainDescription, // حفظ الوصف كنص عادي
                'features'    => json_encode($specificDetails, JSON_UNESCAPED_UNICODE), // حفظ الخصائص كـ JSON
                'video_path'  => $videoPath,
            ]);
            $savedImagesMap = [];
            // 🌟 معالجة وحفظ صور الأعمال السابقة (إن وجدت مرسلة في التعديل)
            if ($request->hasFile('previous_work_images')) {
                foreach ($request->file('previous_work_images') as $imageFile) {
                    $path = $imageFile->store('services/images', 'public');
                    ServiceImage::create([
                        'provider_service_id' => $providerService->id,
                        'image_path'          => $path,
                        'price'               => null,
                        'title'               => 'أعمال سابقة' // أو أي عنوان مناسب
                    ]);
                }
            }
            // ز) 🌟 رفع الصور الإضافية الجديدة (مع مراعاة الأسعار والعناوين للمنسق والكيك)
            if ($request->has('images')) {
                if (in_array($serviceType, ['decoration', 'cake'])) {
                    foreach ($request->file('images') as $index => $imageGroup) {
                        if (isset($imageGroup['file'])) {
                            $path = $imageGroup['file']->store('services/images', 'public');
                            $serviceImage = ServiceImage::create([
                                'provider_service_id' => $providerService->id,
                                'image_path'          => $path,
                                'price'               => $validatedData['images'][$index]['price'] ?? 0,
                                'title'               => $validatedData['images'][$index]['title'] ?? ''
                            ]);
                            $savedImagesMap[$index] = $serviceImage->id;
                        }
                    }
                } else {
                    foreach ($request->file('images') as $imageFile) {
                        $path = $imageFile->store('services/images', 'public');
                        ServiceImage::create([
                            'provider_service_id' => $providerService->id,
                            'image_path'          => $path,
                            'price'               => null
                        ]);
                    }
                }
            }
            // ح) تحديث الباقات الآمن (بدون كسر حجوزات بتول)
            if ($request->has('packages')) {
                $oldPackageIds = DB::table('package_services')
                    ->where('provider_service_id', $providerService->id)
                    ->pluck('package_id')
                    ->toArray();
                // فك ارتباط الباقات القديمة بهذه الخدمة
                DB::table('package_services')->where('provider_service_id', $providerService->id)->delete();
                // تحويل الباقات القديمة إلى inactive
                if (!empty($oldPackageIds)) {
                    Package::whereIn('id', $oldPackageIds)->update(['status' => 'inactive']);
                }
                // إنشاء الباقات الجديدة
                foreach ($validatedData['packages'] as $packageData) {
                    $packageCustomDescription = null;
                    // 🌟 معالجة وصف الباقة حسب نوع الخدمة (كما في دالة الإضافة)
                    if ($serviceType === 'photographer') {
                        $photographerSpecs = [
                            'photos_count'   => $packageData['photos_count'] ?? 0,
                            'video_duration' => $packageData['video_duration'] ?? null,
                            'video_quality'  => $packageData['video_quality'] ?? null,
                        ];
                        $packageCustomDescription = json_encode($photographerSpecs, JSON_UNESCAPED_UNICODE);
                    } elseif ($serviceType === 'cake') {
                        $packageCustomDescription = $packageData['description'] ?? null;
                    } elseif ($serviceType === 'hall') {
                        $packageCustomDescription = $packageData['description'] ?? null; // 🌟 أضيفي هذا السطر فقط هنا
                    } elseif ($serviceType === 'decoration') {
                        $packageCustomDescription = $packageData['description'] ?? null; // 🌟 إضافة وصف باقة المنسق هنا أيضاً
                    }
                    $package = Package::create([
                        'user_id'     => $providerId,
                        'name'        => $packageData['name'],
                        'description' => $packageCustomDescription,
                        'price'       => $packageData['price'],
                        'status'      => 'active'
                    ]);
                    // ربط الباقة الجديدة
                    DB::table('package_services')->insert([
                        'provider_service_id' => $providerService->id,
                        'package_id'          => $package->id,
                        'created_at'          => now(),
                        'updated_at'          => now()
                    ]);
                    // 🌟 ربط الصور المحددة بالباقة الجديدة (للكيك والمنسق)
                    if (in_array($serviceType, ['decoration', 'cake']) && !empty($packageData['image_indices'])) {
                        foreach ($packageData['image_indices'] as $imgIndex) {
                            if (isset($savedImagesMap[$imgIndex])) {
                                DB::table('package_images')->insert([
                                    'package_id'       => $package->id,
                                    'service_image_id' => $savedImagesMap[$imgIndex],
                                    'created_at'       => now(),
                                    'updated_at'       => now()
                                ]);
                            }
                        }
                    }
                    // 🌟 [إضافة جديدة وآمنة]: ربط الصور الموجودة مسبقاً في النظام بالباقة (خاص بالمنسق decoration فقط)
                    if ($serviceType === 'decoration' && !empty($packageData['image_ids'])) {
                        foreach ($packageData['image_ids'] as $existingImageId) {
                            DB::table('package_images')->insert([
                                'package_id'       => $package->id,
                                'service_image_id' => $existingImageId,
                                'created_at'       => now(),
                                'updated_at'       => now()
                            ]);
                        }
                    }
                    // ربط الميزات
                    if (!empty($packageData['feature_ids'])) {
                        foreach ($packageData['feature_ids'] as $featureId) {
                            DB::table('package_features')->insert([
                                'package_id' => $package->id,
                                'feature_id' => $featureId,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                }
            }
            DB::commit();
            return response()->json([
                'status'  => true,
                'message' => ('messages.service_updated_success'),
                'data'    => $providerService->load(['service', 'images', 'location.city', 'packages.images'])
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[UPDATE PROVIDER SERVICE ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => ('messages.error_updating_service')
            ], 500);
        }
    }
    public function destroy($id)
    {
        $providerId = Auth::id();
        $providerService = ProviderService::where('id', $id)
            ->where('user_id', $providerId)
            ->first();
        if (!$providerService) {
            return response()->json([
                'status'  => false,
                'message' => ('messages.service_destroy_unauthorized_or_not_found')
            ], 403);
        }
        if ($providerService->status === 'inactive') {
            return response()->json([
                'status'  => false,
                'message' => ('messages.service_already_inactive')
            ], 400);
        }
        DB::beginTransaction();
        try {
            $providerService->update([
                'status' => 'inactive'
            ]);
            $packageIds = DB::table('package_services')
                ->where('provider_service_id', $providerService->id)
                ->pluck('package_id')
                ->toArray();
            if (!empty($packageIds)) {
                Package::whereIn('id', $packageIds)->update(['status' => 'inactive']);
            }
            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => ('messages.service_deleted_success')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[DESTROY PROVIDER SERVICE ARCHIVE ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => ('messages.error_deleting_service')
            ], 500);
        }
    }
    //للموقع 
     public function index1()
    {
        try {
            $providerId = Auth::id();

            // 1. جلب الخدمات الخاصة بالمزود الحالي
            $myServices = ProviderService::with(['service', 'location.city', 'images'])
                ->where('user_id', $providerId)
                ->latest()
                ->get();

            // 2. استخراج معرفات الخدمات (Service IDs) ومعرفات المدن (City IDs) الخاصة بالمزود الحالي لمعرفة مجاله ومكانه
            $myServiceIds = $myServices->pluck('service_id')->unique()->toArray();

            // استخراج الـ city_id من علاقة الـ location لكل خدمة من خدماته
            $myCityIds = $myServices->pluck('location.city_id')->unique()->filter()->toArray();

                                             // 3. جلب خدمات المنافسين بناءً على الفلترة التلقائية (نفس النوع ونفس المدينة)
            $competitorServices = collect(); // مصفوفة فارغة افتراضياً في حال لم يكن للمزود خدمات بعد

            if (! empty($myServiceIds) && ! empty($myCityIds)) {
                $competitorServices = ProviderService::with(['service', 'location.city', 'images'])
                    ->where('user_id', '!=', $providerId)  // استبعاد خدمات المزود نفسه
                    ->whereIn('service_id', $myServiceIds) // نفس نوع الخدمة (صالة، تصوير، كيك...)
                    ->whereHas('location', function ($query) use ($myCityIds) {
                        $query->whereIn('city_id', $myCityIds); // نفس المدينة
                    })
                    ->latest()
                    ->get();
            } else {
                // ميزة إضافية: إذا كان المزود جديداً كلياً وليس لديه خدمات بعد، نقترح عليه آخر الخدمات العامة في النظام كمنافسين
                $competitorServices = ProviderService::with(['service', 'location.city', 'images'])
                    ->where('user_id', '!=', $providerId)
                    ->latest()
                    ->take(10) // جلب آخر 10 خدمات عامة كأمثلة له
                    ->get();
            }

            // 4. إعادة البيانات مقسمة ومنظمة بشكل رائع للـ Frontend
            return response()->json([
                'status'  => true,
                'message' => 'تم جلب لوحة التحكم بنجاح شاملة خدماتك والمنافسين.',
                'data'    => [
                    'my_services'         => $myServices,         // خدمات المزود نفسه
                    'competitor_services' => $competitorServices, // خدمات المنافسين (فلترة حسب النوع والمكان)
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('[PROVIDER DASHBOARD INDEX SMART ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ تقني أثناء جلب بيانات لوحة التحكم.',
            ], 500);
        }
    }

}
