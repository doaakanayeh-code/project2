<?php
namespace Database\Seeders;

use App\Models\City;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\Feature;
use App\Models\Location;
use App\Models\Package;
use App\Models\ProviderService;
use App\Models\ProviderServiceVideo;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private array $normalUsers = [
        ['username' => 'ahmad_user', 'email' => 'ahmad.user@example.com'],
        ['username' => 'lama_user', 'email' => 'lama.user@example.com'],
    ];

    private array $providers = [
        ['username' => 'sara_provider', 'phone' => '0991111111'],
        ['username' => 'lina_provider', 'phone' => '0991111112'],
        ['username' => 'omar_provider', 'phone' => '0991111113'],
    ];


    private array $servicesToCreatePerProvider = [];
    private array $hallGroups                  = [
        [
            'name'         => 'صالة بلازا',
            'city_name'    => 'دمشق',
            'video'        => '13',
            'image_prefix' => 'plaze',
            'address'      => 'دمشق - المزة',
        ],
        [
            'name'         => 'صالة النور',
            'city_name'    => 'دمشق',
            'video'        => '12',
            'image_prefix' => 'nor',
            'address'      => 'دمشق - الميدان',
        ],
        [
            'name'         => 'صالة الأوسكار',
            'city_name'    => 'دمشق',
            'video'        => '11',
            'image_prefix' => 'oscar',
            'address'      => 'دمشق - المزرعة',
        ],
        [
            'name'         => 'صالة النفرتيتي',
            'city_name'    => 'دمشق',
            'video'        => '8',
            'image_prefix' => 'nf',
            'address'      => 'دمشق -  المزة',
        ],
        [
            'name'         => 'صالة الأزورد',
            'city_name'    => 'دمشق',
            'video'        => '3',
            'image_prefix' => 'lazord',
            'address'      => 'دمشق - مساكن برزة',
        ],
        [
            'name'         => 'صالة التيسير',
            'city_name'    => 'دمشق',
            'video'        => '6',
            'image_prefix' => 'tayser',
            'address'      => 'دمشق - كفرسوسة ',
        ],
        [
            'name'         => 'صالة لونا',
            'city_name'    => 'دمشق',
            'video'        => 'no_number',
            'image_prefix' => 'lona',
            'address'      => 'دمشق - ضاحية قدسيا ',
        ],
    ];
    private array $decorationGroups = [
        [
            'name'         => ' decoration yara  ',
            'city_name'    => 'دمشق',
            'image_prefix' => 'd',
            'video_prefix' => 'd',
            'address'      => 'دمشق - المزة',
        ],
        [
            'name'         => 'تنسيق أعياد ميلاد',
            'city_name'    => 'حمص',
            'image_prefix' => 'h',
            'video_prefix' => 'h',
            'address'      => 'حمص -الحمدانية',
        ],
        [
            'name'         => 'تنسيق حفلات توديع العزوبية ',
            'city_name'    => 'إدلب',
            'image_prefix' => 'v',
            'video_prefix' => 'v',
            'address'      => 'ادلب-الدانة',
        ],
        [
            'name'          => 'bitthday decoration   ',
            'city_name'     => 'دمشق',
            'video_keyword' => 'sample_video9',
            'image_keyword' => 'birthday',
            'address'       => 'دمشق-ركن الدين',
        ],
        [
            'name'         => 'تنسيق الصالات  ',
            'city_name'    => 'اللاذقية',
            'image_prefix' => 'y',
            'address'      => 'المدينة-اللاذقية ',
        ],
        [
            'name'          => 'تنسيق حفلات ',
            'city_name'     => 'دمشق',
            'image_keyword' => 'ty',
            'address'       => 'دمشق-ضاحية قدسيا',
        ],
        [
            'name'                => ' lona decoration ',
            'city_name'           => 'دمشق',
            'video_keyword'       => 'حجاب',
            'image_keyword'       => 'حجاب',
            'video_keyword_index' => 0,
            'address'             => 'دمشق-ركن الدين',
        ],
        [
            'name'                => ' new style decoration ',
            'city_name'           => 'حمص',
            'video_keyword'       => 'مملكتنا',
            'image_keyword'       => 'أميرة',
            'video_keyword_index' => 0,
            'address'             => 'حمص-الوعر',
        ],
        [
            'name'          => ' كوشات خطوبة  ',
            'city_name'     => 'حماة',
            'video_keyword' => 'sample_video5',
            'image_keyword' => 'new',
            'address'       => 'حماة -السلمية',
        ],
    ];
    private array $cakeGroups = [
        [
            'name'           => 'كيك الأفراح الملكي',
            'city_name'      => 'دمشق',
            'address'        => 'دمشق - المزة',
            'image_keywords' => ['screenshot'],
        ],
        [
            'name'           => 'كيك الخطوبة الفاخر',
            'city_name'      => 'حلب',
            'address'        => 'حلب - الشهباء',
            'image_keywords' => ['t1.', 't2.', 't3.', 't4.'],
        ],
        [
            'name'           => 'صينية حلويات المناسبات',
            'city_name'      => 'حمص',
            'address'        => 'حمص - الوعر',
            'image_keywords' => ['t5.', 't6.', 't7.', 't8.', 'v6.'],
        ],
        [
            'name'           => 'كيك أعياد الميلاد الوردي',
            'city_name'      => 'اللاذقية',
            'address'        => 'اللاذقية - الشاطئ الأزرق',
            'image_keywords' => ['جهزنا لكم', 'جزء من كيكات'],
        ],
    ];

    private array $musicGroups = [
        [
            'name'                => 'استديو نادر',
            'city_name'           => 'حمص',
            'address'             => 'حمص - كرم الزيتون',
            'video_prefix'        => 'p',
            'extra_video_keyword' => 'p7',
            'image_prefix'        => 'نادر',
            'band_type'           => 'male',
        ],
        [
            'name'                => 'المنشد علاء الغفير',
            'city_name'           => 'دمشق',
            'address'             => 'دمشق - الميدان',
            'image_prefix'        => 'دمشق',
            'video_keyword'       => 'داريا',
            'extra_video_keyword' => 'الكسوه',
            'band_type'           => 'male',
        ],
        [
            'name'                => 'المنشد محمد برنية',
            'city_name'           => 'دمشق',
            'address'             => 'دمشق - القنوات',
            'image_prefix'        => 'عطر',
            'video_keyword'       => 'شهر',
            'extra_video_keyword' => 'عطر',
            'band_type'           => 'male',
        ],
        [
            'name'         => 'فرقة طيبة',
            'city_name'    => 'دمشق',
            'address'      => 'دمشق - برزة',
            'image_prefix' => 'H8',
            'band_type'    => 'male',
            'occasion'     => 'حفلات دينية',
            // بدون أي ملفات عن قصد - خدمة فاضية
        ],
    ];

    private array $workingDaysOptions = [
        ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء'],
        ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'],
        ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس'],
    ];

    private array $workingHoursOptions = [
        ['from' => '09:00', 'to' => '18:00'],
        ['from' => '10:00', 'to' => '22:00'],
        ['from' => '08:00', 'to' => '20:00'],
    ];

    private array $publicEventAddresses = [
        'نهائي دوري رواد المساجد'    => 'دمشق',
        'معرض بغداد الدولي للكتاب'   => 'مدينة المعارض - طريق المطار',
        'من معرض دمشق الدولي للكتاب' => 'مدينة المعارض - طريق المطار',
        'مهرجان صيف قلعة دمشق'       => 'قلعة دمشق',

    ];
    private array $publicEventCoordinates = [
        'نهائي دوري رواد المساجد'    => ['lat' => 33.5138, 'lng' => 36.2765],   // دمشق (مركز عام)
        'معرض بغداد الدولي للكتاب'   => ['lat' => 33.4890, 'lng' => 36.2377],  // مدينة المعارض - طريق المطار
        'من معرض دمشق الدولي للكتاب' => ['lat' => 33.4890, 'lng' => 36.2377], // نفس مدينة المعارض
        'مهرجان صيف قلعة دمشق'       => ['lat' => 33.5119, 'lng' => 36.3068],      // قلعة دمشق (إحداثيات حقيقية)
    ];
    private array $musicTypesPool      = ['arada', 'nasheed', 'dj'];
    private array $bandTypeOptions     = ['male', 'female', 'mixed'];
    private array $deliveryTypeOptions = ['delivery', 'pickup', 'both'];

    private array $hallEventTypesPool = ['زفاف', 'خطوبة', 'تخرج', 'عيد ميلاد'];
    private array $hallFacilitiesPool = ['موقف سيارات', 'إضاءة احترافية', 'صوتيات', 'تكييف مركزي', 'مصعد',' ليزر سلو','برومو','شرارة','صحن فرنسي','شاشة عرض','اضاءة dance floor','سيارة ليموزين','كيك طبيعي ','ضباب ارضي '];

    private array $cakeColorsPool  = ['أبيض', 'ذهبي', 'وردي', 'أحمر', 'أزرق فاتح'];
    private array $cakeFillingPool = ['شوكولا', 'فانيلا', 'فراولة', 'كراميل', 'بستاشيو'];
    private array $cakeFlavorPool  = ['فانيلا كلاسيك', 'ريد فيلفيت', 'ليمون', 'موكا'];

    private array $reviewCommentsByRating = [
        5 => [
            'تجربة رائعة جداً، بالضبط متل ما توقعت وأكتر!',
            'خدمة احترافية 100%, بنصح فيها لأي حدا.',
            'كل التفاصيل كانت مرتبة والالتزام بالوقت ممتاز، شكراً كتير.',
            'فاقت التوقعات فعلاً، رح أتعامل معهم بمناسبات جاية أكيد.',
        ],
        4 => [
            'خدمة جيدة كتير، بس في تفاصيل بسيطة ممكن تتحسن شوي.',
            'راضية عن التجربة بشكل عام، وتعامل محترم.',
            'النتيجة حلوة والسعر مناسب مقارنة بالجودة.',
            'كانت تجربة إيجابية، بس التواصل تأخر شوي بالبداية.',
        ],
        3 => [
            'الخدمة كانت متوسطة، توقعت أحسن شوي بصراحة.',
            'مقبولة، بس في مجال حقيقي للتحسين بالتنظيم.',
            'عادية، ما في شي مميز بس ولا في شي سيء.',
        ],
    ];

    // ✅ إحداثيات المدن الرئيسية (كانت معرّفة بدون استخدام - هلق صارت مفعّلة)
    private array $cityCoordinates = [
        'دمشق'     => ['lat' => 33.5138, 'lng' => 36.2765],
        'حلب'      => ['lat' => 36.2021, 'lng' => 37.1343],
        'حمص'      => ['lat' => 34.7324, 'lng' => 36.7137],
        'اللاذقية' => ['lat' => 35.5317, 'lng' => 35.7915],
        'إدلب'     => ['lat' => 35.9310, 'lng' => 36.6339],
        'حماة'     => ['lat' => 35.1318, 'lng' => 36.7578],
        'كفرسوسة'  => ['lat' => 33.4900, 'lng' => 36.2800],
    ];

    private function randomCoordinatesFor(?string $cityName): array
    {
        $base = $this->cityCoordinates[$cityName] ?? $this->cityCoordinates['دمشق'];
        return [
            'lat' => $base['lat'] + (mt_rand(-50, 50) / 10000),
            'lng' => $base['lng'] + (mt_rand(-50, 50) / 10000),
        ];
    }

    // ✅ دالة مساعدة: بتجيب اسم المدينة من الـ id، مع fallback لـ "دمشق"
    // إذا الـ id مش موجود أو null. مستخدمة بكل مكان معنا بس cityId رقمي
    // وما معنا كائن City جاهز بالسكوب.
    private function cityNameFromId(?int $cityId): string
    {
        if (! $cityId) {
            return 'دمشق';
        }
        return City::find($cityId)->name ?? 'دمشق';
    }

    public function run(): void
    {
        $cities = $this->resolveCities();
        $this->ensureFeaturesExist();

        foreach ($this->normalUsers as $u) {
            $user = User::create([
                'username'          => $u['username'],
                'email'             => $u['email'],
                'phone'             => null,
                'password'          => Hash::make('Password123'),
                'role'              => 'user',
                'id_img_front'      => $this->getAssetFromLocalFolder('images', 'ids/front'),
                'id_img_back'       => $this->getAssetFromLocalFolder('images', 'ids/back'),
                'email_verified_at' => now(),
            ]);

            Wallet::create(['user_id' => $user->id, 'balance' => 0.00]);
            $this->command->info("✔ يوزر عادي: {$user->username} (id: {$user->id})");
        }

        $providerModels = [];

        foreach ($this->providers as $p) {
            $providerUser = User::create([
                'username'          => $p['username'],
                'email'             => null,
                'phone'             => $p['phone'],
                'password'          => Hash::make('Password123'),
                'role'              => 'provider',
                'id_img_front'      => $this->getAssetFromLocalFolder('images', 'ids/front'),
                'id_img_back'       => $this->getAssetFromLocalFolder('images', 'ids/back'),
                'email_verified_at' => now(),
            ]);
            // ----------------------------------------------------

            Wallet::create(['user_id' => $providerUser->id, 'balance' => 0.00]);
            $providerModels[] = $providerUser;
            $this->command->info("✔ مزوّد: {$providerUser->username} (id: {$providerUser->id})");

            foreach ($this->servicesToCreatePerProvider as $serviceType => $count) {
                for ($i = 1; $i <= $count; $i++) {
                    $city = $cities->random();
                    $this->createProviderService($providerUser->id, $city->id, $serviceType, $i);
                }
            }
        }
        // إنشاء 7 صالات فقط - بدون تكرار
// ----------------------------------------------------
        foreach ($this->hallGroups as $index => $group) {
            $provider = $providerModels[$index % count($providerModels)];
            $city     = City::firstOrCreate(['name' => $group['city_name'] ?? 'دمشق']);
            $this->createHallService($provider->id, $city->id, $group, $index + 1);
        }

        foreach ($this->decorationGroups as $index => $group) {
            $provider = $providerModels[$index % count($providerModels)];
            $city     = City::firstOrCreate(['name' => $group['city_name'] ?? 'دمشق']);
            $this->createDecorationService($provider->id, $city->id, $group);
        }

        foreach ($this->cakeGroups as $index => $group) {
            $provider = $providerModels[$index % count($providerModels)];
            $city     = City::firstOrCreate(['name' => $group['city_name'] ?? 'دمشق']);
            $this->createCakeService($provider->id, $city->id, $group);
        }

        // ----------------------------------------------------
        // الـ 4 خدمات موسيقى/إنشاد المخصصة (استديو نادر، منشدين، فرقة طيبة)
        // موزّعة على المزوّدين، كل وحدة بمدينتها وعنوانها وملفاتها الخاصة
        // ----------------------------------------------------
        foreach ($this->musicGroups as $index => $group) {
            $provider = $providerModels[$index % count($providerModels)];
            $this->createMusicService($provider->id, $cities, $group);
        }

        $damascusCity   = $cities->where('name', 'دمشق')->first() ?? $cities->random();
        $idlibCity      = City::firstOrCreate(['name' => 'إدلب']);
        $kafrSousseCity = City::firstOrCreate(['name' => 'كفرسوسة']);

        $p1 = $providerModels[0]->id;
        $p2 = ($providerModels[1] ?? $providerModels[0])->id;
        $p3 = ($providerModels[2] ?? $providerModels[0])->id;

        // ----------------------------------------------------
        // تخصيص خدمات التصوير الـ 6 حسب الشروط بدقة
        // ----------------------------------------------------

        // ✅ الإحداثيات هون كانت أصلاً يدوية وصحيحة (مش عشوائية حوالين
        // دمشق)، فما لمسناها - هاد الجزء تمام من الأساس.

        // 1. صور حرف K (المكان: أبو رمانة)
        $locAbuRommaneh = Location::create([
            'city_id'      => $damascusCity->id,
            'address_name' => 'أبو رمانة، دمشق',
            'latitude'     => 33.5138,
            'longitude'    => 36.2765,
        ]);
        $this->createTargetedPhotographerService($p1, $locAbuRommaneh->id, 'استوديو رويال أبو رمانة', 'k_images');

        // 2. صور الأسماء العربية (المكان: باب توما)
        $locBabTouma = Location::create([
            'city_id'      => $damascusCity->id,
            'address_name' => 'باب توما، دمشق',
            'latitude'     => 33.5102,
            'longitude'    => 36.3090,
        ]);
        $this->createTargetedPhotographerService($p2, $locBabTouma->id, 'استوديو ياسمين باب توما', 'arabic_text');

        // 3. صور "قديش حلوين" (جلسات تصوير عامة - بالضبط 4 صور)
        $locMkal = Location::create([
            'city_id'      => $damascusCity->id,
            'address_name' => 'دمشق - المزة',
            'latitude'     => 33.5000,
            'longitude'    => 36.2600,
        ]);
        $this->createTargetedPhotographerService($p3, $locMkal->id, 'جلسات تصوير عامة', 'how_sweet_shots', null, null, 4);

        // 4. الصور المحددة في كفرسوسة (العدد 15 صورة من FB_IMG)
        $locKafrSousse = Location::create([
            'city_id'      => $kafrSousseCity->id,
            'address_name' => 'كفرسوسة - ساحة المحافظة',
            'latitude'     => 33.4900,
            'longitude'    => 36.2800,
        ]);
        $this->createTargetedPhotographerService($p1, $locKafrSousse->id, 'استوديو كفرسوسة الاحترافي', 'kafr_sousse_selected', null, null, 15);

        // 5. فيديو رقم 1 (تصوير بروبوزل - المكان: إدلب - بدون صور أبداً)
        $locIdlib = Location::create([
            'city_id'      => $idlibCity->id,
            'address_name' => 'إدلب - وسط المدينة',

            'latitude'     => 35.9310,
            'longitude'    => 36.6339,
        ]);
        $this->createTargetedPhotographerService($p2, $locIdlib->id, 'استوديو اللحظات الخاصة للبروبوزل', 'k6_image', 'video_1', null, 1);

        // 6. الفيديو الذي بدون رقم (تصوير أماكن دينية - بدون صور أبداً) مع فيديو رقم 7
        $locReligious = Location::create([
            'city_id'      => $damascusCity->id,
            'address_name' => 'دمشق - الشام القديمة',
            'latitude'     => 33.5130,
            'longitude'    => 36.3000,
        ]);
        $this->createTargetedPhotographerService($p3, $locReligious->id, 'استوديو المعالم الدينية والتراثية', 'k7_image', 'video_2', 'video_7', 1);

        // ----------------------------------------------------
        // الفعاليات العامة الأربعة (بمنطق الصور/الفيديو المخصص لكل فعالية)
        // ----------------------------------------------------
        $publicEventsList = [
            1 => 'نهائي دوري رواد المساجد',
            2 => 'معرض بغداد الدولي للكتاب',
            3 => 'من معرض دمشق الدولي للكتاب',
            4 => 'مهرجان صيف قلعة دمشق',
        ];

        foreach ($publicEventsList as $index => $eventName) {
            $assignedProvider = $providerModels[($index - 1) % count($providerModels)]->id;
            $city             = $cities->random();
            $this->createProviderService($assignedProvider, $city->id, 'public_event', $index, $eventName);
        }

        $this->seedBookingsAndReviews();
        $this->ensureAllServicesHaveReviews();
        $this->command->info('✔ تم إنشاء كل المزودين والخدمات والباقات والملفات والتقييمات بنجاح.');
    }

    // ==================================================================
    // قسم الكيك (cake) - 4 خدمات مخصصة، كل صورة إلها سعرها الخاص
    // ==================================================================

    private function createCakeService(int $providerId, int $cityId, array $group): void
    {
        DB::transaction(function () use ($providerId, $cityId, $group) {
            $baseService = Service::firstOrCreate(
                ['service_type' => 'cake'],
                ['name' => 'كيك مناسبات']
            );

            // ✅ إحداثيات دقيقة حسب المدينة الفعلية (city_id) بدل الإحداثيات
            // الثابتة حوالين دمشق يلي كانت مستخدمة قبل
            $coords = $this->randomCoordinatesFor($this->cityNameFromId($cityId));

            $location = Location::create([
                'city_id'      => $cityId,
                'address_name' => $group['address'] ?? "عنوان تجريبي - {$group['name']}",
                'latitude'  => $coords['lat'],
                'longitude' => $coords['lng'],
            ]);

            $workingDays  = $this->workingDaysOptions[array_rand($this->workingDaysOptions)];
            $workingHours = $this->workingHoursOptions[array_rand($this->workingHoursOptions)];
            $finalPrice   = mt_rand(30, 150);

            $specificDetails = [
                'working_days'  => $workingDays,
                'working_hours' => $workingHours,
                'colors'        => $this->randomSubset($this->cakeColorsPool, 4, 5),
                'filling'       => $this->randomSubset($this->cakeFillingPool, 4, 5),
                'flavor'        => $this->randomSubset($this->cakeFlavorPool, 3, 4),
                'quantity'      => mt_rand(10, 50),
                'description'   => "كيك {$group['name']} مصنوع بمكونات طازجة، مناسب لكل المناسبات الخاصة والعامة.",
                'delivery_type' => $this->deliveryTypeOptions[array_rand($this->deliveryTypeOptions)],
            ];

            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'name'        => $group['name'],
                'service_id'  => $baseService->id,
                'location_id' => $location->id,
                'price'       => $finalPrice,
                'description' => json_encode($specificDetails, JSON_UNESCAPED_UNICODE),
                'video_path'  => null,
                'status'      => 'active',
            ]);

            $imageSources   = $this->getCakeImagesByKeywords($group['image_keywords'] ?? []);
            $savedImagesMap = [];

            foreach ($imageSources as $imgIndex => $imgSource) {
                $imgStoragePath = $this->copyDemoAsset($imgSource, 'services/images');
                if (! $imgStoragePath) {
                    continue;
                }

                $serviceImage = ServiceImage::create([
                    'provider_service_id' => $providerService->id,
                    'image_path'          => $imgStoragePath,
                    'price'               => mt_rand(15, 60),
                    'title'               => "صورة {$group['name']} " . ($imgIndex + 1),
                ]);
                $savedImagesMap[$imgIndex] = $serviceImage->id;
            }

            // الباقات الثلاث
            $tiers = [
                ['label' => 'أساسية', 'multiplier' => 1.0, 'features' => ['خدمة أساسية', 'دعم فني عادي']],
                ['label' => 'قياسية', 'multiplier' => 1.5, 'features' => ['كل مميزات الأساسية', 'إضافات متوسطة', 'أولوية بالحجز']],
                ['label' => 'مميزة', 'multiplier' => 2.2, 'features' => ['كل المميزات السابقة', 'خدمة VIP', 'دعم فني على مدار الساعة']],
            ];

            foreach ($tiers as $tier) {
                $packagePrice = round(max($finalPrice, 20) * $tier['multiplier'], 2);

                $package = Package::create([
                    'user_id' => $providerId,
                    'name'    => "باقة {$tier['label']} - {$group['name']}",
                    'price'       => $packagePrice,
                    'description' => "باقة {$tier['label']} - {$group['name']}: " . implode('، ', $tier['features']),
                    'status' => 'active',
                ]);

                DB::table('package_services')->insert([
                    'provider_service_id' => $providerService->id,
                    'package_id'          => $package->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                if (! empty($savedImagesMap)) {
                    $subsetImages = array_slice($savedImagesMap, 0, min(2, count($savedImagesMap)), true);
                    foreach ($subsetImages as $imgId) {
                        DB::table('package_images')->insert([
                            'package_id'       => $package->id,
                            'service_image_id' => $imgId,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }
            }

            $imagesCount = count($savedImagesMap);
            $this->command->info("  ↳ {$group['name']} (cake): {$imagesCount} صور مسعّرة فردياً، 3 باقات.");
        });
    }

    /**
     * بيجيب كل ملفات الصور يلي اسمها فيه أي كلمة من لائحة الكلمات المفتاحية المعطاة.
     */
    private function getCakeImagesByKeywords(array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        $files         = Storage::disk('public')->allFiles('demo_assets');
        $matched       = [];
        $extensions    = ['jpg', 'jpeg', 'png', 'webp'];
        $lowerKeywords = array_map('mb_strtolower', $keywords);

        foreach ($files as $file) {
            $filename = mb_strtolower(basename($file));
            $ext      = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, $extensions)) {
                continue;
            }

            foreach ($lowerKeywords as $keyword) {
                if (str_contains($filename, $keyword)) {
                    $matched[] = $file;
                    break;
                }
            }
        }

        return $matched;
    }

    // ==================================================================
    // قسم الموسيقى/الإنشاد (music) - 4 خدمات مخصصة بمدن/عناوين وملفات محددة
    // ==================================================================

    private function createMusicService(int $providerId, $cities, array $group): void
    {
        DB::transaction(function () use ($providerId, $cities, $group) {
            $baseService = Service::firstOrCreate(
                ['service_type' => 'music'],
                ['name' => 'فرقة موسيقية']
            );

            $cityName = $group['city_name'] ?? 'دمشق';
            $city     = $cities->where('name', $cityName)->first() ?? City::firstOrCreate(['name' => $cityName]);

            // ✅ إحداثيات دقيقة حسب اسم المدينة (city_name) - هون كان
            // عندنا اسم المدينة جاهز أصلاً بالسكوب، فقط ناقص الاستخدام
            $coords = $this->randomCoordinatesFor($cityName);

            $location = Location::create([
                'city_id'      => $city->id,
                'address_name' => $group['address'] ?? "عنوان تجريبي - {$group['name']}",
                'latitude'  => $coords['lat'],
                'longitude' => $coords['lng'],
            ]);

            $pricePerHour    = mt_rand(50, 250);
            $specificDetails = [
                'music_types'    => $this->randomSubset($this->musicTypesPool, 1, 3),
                'band_type'      => $group['band_type'] ?? 'male',
                'price_per_hour' => $pricePerHour,
            ];

            if (isset($group['occasion'])) {
                $specificDetails['occasion'] = $group['occasion'];
            }

            // الملف الرئيسي (صوت m4a أو فيديو)
            $videoSource = $this->resolveMusicVideo($group);
            $videoPath   = $videoSource
                ? $this->copyDemoAsset($videoSource, 'services/videos')
                : null;

            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'name'        => $group['name'],
                'service_id'  => $baseService->id,
                'location_id' => $location->id,
                'price'       => $pricePerHour,
                'description' => json_encode($specificDetails, JSON_UNESCAPED_UNICODE),
                'video_path'  => $videoPath,
                'status'      => 'active',
            ]);

            // ملف إضافي (لو موجود) بينحفظ بجدول منفصل
            $extraVideoSource = $this->resolveMusicExtraVideo($group);
            if ($extraVideoSource) {
                $extraVideoPath = $this->copyDemoAsset($extraVideoSource, 'services/videos');
                if ($extraVideoPath) {
                    ProviderServiceVideo::create([
                        'provider_service_id' => $providerService->id,
                        'video_path'          => $extraVideoPath,
                        'title'               => "ملف إضافي - {$group['name']}",
                    ]);
                }
            }

            // الصور - كل صورة إلها سعر خاص فيها
            $imageSources   = $this->resolveMusicImages($group);
            $savedImagesMap = [];

            foreach ($imageSources as $imgIndex => $imgSource) {
                $imgStoragePath = $this->copyDemoAsset($imgSource, 'services/images');
                if (! $imgStoragePath) {
                    continue;
                }

                $serviceImage = ServiceImage::create([
                    'provider_service_id' => $providerService->id,
                    'image_path'          => $imgStoragePath,
                    'price'               => mt_rand(20, 100),
                    'title'               => "صورة {$group['name']} " . ($imgIndex + 1),
                ]);
                $savedImagesMap[$imgIndex] = $serviceImage->id;
            }

            // الباقات الثلاث
            $tiers = [
                ['label' => 'أساسية', 'multiplier' => 1.0, 'features' => ['خدمة أساسية', 'دعم فني عادي']],
                ['label' => 'قياسية', 'multiplier' => 1.5, 'features' => ['كل مميزات الأساسية', 'إضافات متوسطة', 'أولوية بالحجز']],
                ['label' => 'مميزة', 'multiplier' => 2.2, 'features' => ['كل المميزات السابقة', 'خدمة VIP', 'دعم فني على مدار الساعة']],
            ];

            foreach ($tiers as $tier) {
                $packagePrice = round(max($pricePerHour, 20) * $tier['multiplier'], 2);

                $package = Package::create([
                    'user_id' => $providerId,
                    'name'    => "باقة {$tier['label']} - {$group['name']}",
                    'price'       => $packagePrice,
                    'description' => "باقة {$tier['label']} - {$group['name']}: " . implode('، ', $tier['features']),
                    'status'      => 'active',
                ]);

                DB::table('package_services')->insert([
                    'provider_service_id' => $providerService->id,
                    'package_id'          => $package->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $featureIds = Feature::where('service_type', 'music')
                    ->inRandomOrder()
                    ->limit(count($tier['features']))
                    ->pluck('id');

                foreach ($featureIds as $featureId) {
                    DB::table('package_features')->insert([
                        'package_id' => $package->id,
                        'feature_id' => $featureId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (! empty($savedImagesMap)) {
                    $subsetImages = array_slice($savedImagesMap, 0, min(2, count($savedImagesMap)), true);
                    foreach ($subsetImages as $imgId) {
                        DB::table('package_images')->insert([
                            'package_id'       => $package->id,
                            'service_image_id' => $imgId,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }
            }

            $videoNote   = $videoPath ? 'مع ملف صوتي/فيديو رئيسي' : 'بدون أي ملف';
            $extraNote   = $extraVideoSource ? ' + ملف إضافي' : '';
            $imagesCount = count($savedImagesMap);
            $this->command->info("  ↳ {$group['name']} (music): {$imagesCount} صور، 3 باقات، {$videoNote}{$extraNote}.");
        });
    }

    private function resolveMusicVideo(array $group): ?string
    {
        // بنضيف m4a لأنو ملفات الإنشاد صوتية بهاد الامتداد
        $extensions = ['mp4', 'mov', 'avi', 'mkv', 'm4a'];

        if (isset($group['video_prefix'])) {
            $matches = $this->getDecorationFilesByPrefix($group['video_prefix'], $extensions);
            return $matches[0] ?? null;
        }

        if (isset($group['video_keyword'])) {
            $matches = $this->getDecorationFilesByKeyword($group['video_keyword'], $extensions);
            return $matches[0] ?? null;
        }

        return null;
    }

    private function resolveMusicExtraVideo(array $group): ?string
    {
        if (! isset($group['extra_video_keyword'])) {
            return null;
        }

        $extensions = ['mp4', 'mov', 'avi', 'mkv', 'm4a'];
        $matches    = $this->getDecorationFilesByKeyword($group['extra_video_keyword'], $extensions);

        return $matches[0] ?? null;
    }

    private function resolveMusicImages(array $group): array
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (isset($group['image_prefix'])) {
            return $this->getDecorationFilesByPrefix($group['image_prefix'], $extensions);
        }

        if (isset($group['image_keyword'])) {
            return $this->getDecorationFilesByKeyword($group['image_keyword'], $extensions);
        }

        return [];
    }

    // ==================================================================
    // قسم تنسيق الحفلات (decoration) - منطق مستقل بالكامل
    // ==================================================================

    private function createDecorationService(int $providerId, int $cityId, array $group): void
    {
        DB::transaction(function () use ($providerId, $cityId, $group) {
            $baseService = Service::firstOrCreate(
                ['service_type' => 'decoration'],
                ['name' => 'تنسيق حفلات']
            );

            // ✅ إحداثيات دقيقة حسب المدينة الفعلية (city_id) بدل الإحداثيات
            // الثابتة حوالين دمشق يلي كانت مستخدمة قبل
            $coords = $this->randomCoordinatesFor($this->cityNameFromId($cityId));

            $location = Location::create([
                'city_id'      => $cityId,
                'address_name' => $group['address'] ?? "عنوان تجريبي - {$group['name']}",
                'latitude'  => $coords['lat'],
                'longitude' => $coords['lng'],
            ]);

            $workingDays  = $this->workingDaysOptions[array_rand($this->workingDaysOptions)];
            $workingHours = $this->workingHoursOptions[array_rand($this->workingHoursOptions)];
            $finalPrice   = mt_rand(80, 400);

            $specificDetails = [
                'working_days'  => $workingDays,
                'working_hours' => $workingHours,
            ];

            // الفيديو الأساسي
            $primaryVideoSource = $this->resolveDecorationVideo($group);
            $videoPath          = $primaryVideoSource
                ? $this->copyDemoAsset($primaryVideoSource, 'services/videos')
                : null;

            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'name'        => $group['name'],
                'service_id'  => $baseService->id,
                'location_id' => $location->id,
                'price'       => $finalPrice,
                'description' => json_encode($specificDetails, JSON_UNESCAPED_UNICODE),
                'video_path'  => $videoPath,
                'status'      => 'active',
            ]);

            // الفيديو الإضافي (فقط لخدمة "تنسيق حفلات شركات": حجاب + فيديو 10)
            $extraVideoSource = $this->resolveDecorationExtraVideo($group);
            if ($extraVideoSource) {
                $extraVideoPath = $this->copyDemoAsset($extraVideoSource, 'services/videos');
                if ($extraVideoPath) {
                    ProviderServiceVideo::create([
                        'provider_service_id' => $providerService->id,
                        'video_path'          => $extraVideoPath,
                        'title'               => "فيديو إضافي - {$group['name']}",
                    ]);
                }
            }

            // الصور - كل صورة إلها سعر خاص فيها متل التصوير الفوتوغرافي
            // (هيدا المنطق بينطبق على كل مجموعات تنسيق الحفلات، بما فيها "كوشات خطوبة")
            $imageSources   = $this->resolveDecorationImages($group);
            $savedImagesMap = [];

            foreach ($imageSources as $imgIndex => $imgSource) {
                $imgStoragePath = $this->copyDemoAsset($imgSource, 'services/images');
                if (! $imgStoragePath) {
                    continue;
                }
                $serviceImage = ServiceImage::create([
                    'provider_service_id' => $providerService->id,
                    'image_path'          => $imgStoragePath,
                    'price'               => mt_rand(20, 100),
                    'title'               => "صورة تنسيق " . ($imgIndex + 1),
                ]);
                $savedImagesMap[$imgIndex] = $serviceImage->id;
            }

            // الباقات الثلاث
            $tiers = [
                ['label' => 'أساسية', 'multiplier' => 1.0, 'features' => ['خدمة أساسية', 'دعم فني عادي']],
                ['label' => 'قياسية', 'multiplier' => 1.5, 'features' => ['كل مميزات الأساسية', 'إضافات متوسطة', 'أولوية بالحجز']],
                ['label' => 'مميزة', 'multiplier' => 2.2, 'features' => ['كل المميزات السابقة', 'خدمة VIP', 'دعم فني على مدار الساعة']],
            ];

            foreach ($tiers as $tier) {
                $packagePrice = round(max($finalPrice, 20) * $tier['multiplier'], 2);

                $package = Package::create([
                    'user_id' => $providerId,
                    'name'    => "باقة {$tier['label']} - {$group['name']}",
                    'price'       => $packagePrice,
                     'description' => "باقة {$tier['label']} - {$group['name']}: " . implode('، ', $tier['features']),                    'status'      => 'active',
                ]);

                DB::table('package_services')->insert([
                    'provider_service_id' => $providerService->id,
                    'package_id'          => $package->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $featureIds = Feature::where('service_type', 'decoration')
                    ->inRandomOrder()
                    ->limit(count($tier['features']))
                    ->pluck('id');

                foreach ($featureIds as $featureId) {
                    DB::table('package_features')->insert([
                        'package_id' => $package->id,
                        'feature_id' => $featureId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (! empty($savedImagesMap)) {
                    $subsetImages = array_slice($savedImagesMap, 0, min(2, count($savedImagesMap)), true);
                    foreach ($subsetImages as $imgId) {
                        DB::table('package_images')->insert([
                            'package_id'       => $package->id,
                            'service_image_id' => $imgId,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }
            }

            $videoNote   = $videoPath ? 'مع فيديو' : 'بدون فيديو';
            $extraNote   = $extraVideoSource ? ' + فيديو إضافي' : '';
            $imagesCount = count($savedImagesMap);
            $this->command->info("  ↳ {$group['name']} (decoration): {$imagesCount} صور مسعّرة فردياً، 3 باقات، {$videoNote}{$extraNote}.");
        });
    }

    private function resolveDecorationImages(array $group): array
    {
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (isset($group['image_prefix'])) {
            return $this->getDecorationFilesByPrefix($group['image_prefix'], $extensions);
        }

        if (isset($group['image_keyword'])) {
            return $this->getDecorationFilesByKeyword($group['image_keyword'], $extensions);
        }

        return [];
    }

    private function resolveDecorationVideo(array $group): ?string
    {
        $extensions = ['mp4', 'mov', 'avi', 'mkv'];

        if (isset($group['video_prefix'])) {
            $matches = $this->getDecorationFilesByPrefix($group['video_prefix'], $extensions);
            return $matches[0] ?? null;
        }

        if (isset($group['video_keyword'])) {
            $matches = $this->getDecorationFilesByKeyword($group['video_keyword'], $extensions);
            $index   = $group['video_keyword_index'] ?? 0;
            return $matches[$index] ?? null;
        }

        return null;
    }

    private function resolveDecorationExtraVideo(array $group): ?string
    {
        if (! isset($group['extra_video_keyword'])) {
            return null;
        }

        $extensions = ['mp4', 'mov', 'avi', 'mkv'];
        $matches    = $this->getDecorationFilesByKeyword($group['extra_video_keyword'], $extensions);

        return $matches[0] ?? null;
    }

    /**
     * بيجيب كل الملفات يلي اسمها بيبدأ بحرف معيّن متبوع بأرقام اختيارية
     * (متل d1.jpg، d2.mp4، H1.mp4، v3.jpg...) وبنوع امتداد محدد.
     */
    private function getDecorationFilesByPrefix(string $prefix, array $extensions): array
    {
        $files   = Storage::disk('public')->allFiles('demo_assets');
        $matched = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $ext      = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, $extensions)) {
                continue;
            }

            if (preg_match('/^' . preg_quote($prefix, '/') . '\d*\./i', $filename)) {
                $matched[] = $file;
            }
        }

        return $matched;
    }

    /**
     * بيجيب كل الملفات يلي اسمها فيه كلمة/مقطع معيّن (زي "حجاب"، "screenshot"،
     * "fb_img_"، "sample_video9"...) وبنوع امتداد محدد.
     */
    private function getDecorationFilesByKeyword(string $keyword, array $extensions): array
    {
        $files        = Storage::disk('public')->allFiles('demo_assets');
        $matched      = [];
        $lowerKeyword = mb_strtolower($keyword);

        foreach ($files as $file) {
            $filename = mb_strtolower(basename($file));
            $ext      = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, $extensions)) {
                continue;
            }

            if (str_contains($filename, $lowerKeyword)) {
                $matched[] = $file;
            }
        }

        return $matched;
    }

    private function copyDemoAsset(string $originalPath, string $targetFolder): ?string
    {
        if (! Storage::disk('public')->exists($originalPath)) {
            return null;
        }

        $extension   = pathinfo($originalPath, PATHINFO_EXTENSION);
        $newFilename = $targetFolder . '/' . Str::uuid() . '.' . $extension;
        $fileContent = Storage::disk('public')->get($originalPath);
        Storage::disk('public')->put($newFilename, $fileContent);

        return $newFilename;
    }

    // ==================================================================
    // قسم التصوير الفوتوغرافي (photographer) - بدون أي تغيير
    // ==================================================================

    private function createTargetedPhotographerService(int $providerId, int $locationId, string $serviceName, string $categoryKey, ?string $videoMatch1 = null, ?string $videoMatch2 = null, ?int $forceLimitImages = null): void
    {
        DB::transaction(function () use ($providerId, $locationId, $serviceName, $categoryKey, $videoMatch1, $videoMatch2, $forceLimitImages) {
            $baseService = Service::firstOrCreate(
                ['service_type' => 'photographer'],
                ['name' => 'تصوير فوتوغرافي']
            );

            $finalPrice   = mt_rand(50, 150);
            $workingDays  = ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء'];
            $workingHours = ['from' => '09:00', 'to' => '18:00'];

            $specificDetails = [
                'working_days'    => $workingDays,
                'working_hours'   => $workingHours,
                'price_per_hour'  => $finalPrice,
                'processing_time' => '5 أيام عمل',
            ];

            $videoPath = null;
            if ($videoMatch1) {
                $videoPath = $this->getSpecificVideoFile($videoMatch1);
            } elseif ($videoMatch2) {
                $videoPath = $this->getSpecificVideoFile($videoMatch2);
            }

            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'name'        => $serviceName,
                'service_id'  => $baseService->id,
                'location_id' => $locationId,
                'price'       => $finalPrice,
                'description' => json_encode($specificDetails, JSON_UNESCAPED_UNICODE),
                'video_path'  => $videoPath,
                'status'      => 'active',
            ]);

            $imagePaths     = $this->getExclusivePhotographerImages($categoryKey, $forceLimitImages);
            $savedImagesMap = [];

            foreach ($imagePaths as $imgIndex => $imgStoragePath) {
                $serviceImage = ServiceImage::create([
                    'provider_service_id' => $providerService->id,
                    'image_path'          => $imgStoragePath,
                    'price'               => mt_rand(20, 100),
                    'title'               => "صورة احترافية " . ($imgIndex + 1),
                ]);
                $savedImagesMap[$imgIndex] = $serviceImage->id;
            }

            $tiers = [
                ['label' => 'أساسية', 'multiplier' => 1.0],
                ['label' => 'قياسية', 'multiplier' => 1.5],
                ['label' => 'مميزة', 'multiplier' => 2.2],
            ];

            foreach ($tiers as $tier) {
                $packagePrice       = round(max($finalPrice, 20) * $tier['multiplier'], 2);
                $packageDescription = json_encode([
                    'photos_count'   => (int) round(50 * $tier['multiplier']),
                    'video_duration' => mt_rand(1, 3) . ' ساعات',
                    'video_quality'  => $tier['label'] === 'مميزة' ? '4K' : 'HD',
                ], JSON_UNESCAPED_UNICODE);

                $package = Package::create([
                    'user_id' => $providerId,
                    'name'    => "باقة {$tier['label']} - {$serviceName}",
                    'price'       => $packagePrice,
                    'description' => $packageDescription,
                    'status'      => 'active',
                ]);

                DB::table('package_services')->insert([
                    'provider_service_id' => $providerService->id,
                    'package_id'          => $package->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                if (! empty($savedImagesMap)) {
                    $subsetImages = array_slice($savedImagesMap, 0, min(2, count($savedImagesMap)), true);
                    foreach ($subsetImages as $imgId) {
                        DB::table('package_images')->insert([
                            'package_id'       => $package->id,
                            'service_image_id' => $imgId,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }
            }
        });
    }

    private function getExclusivePhotographerImages(string $categoryKey, ?int $forceLimit = null): array
    {
        if ($categoryKey === 'no_images' || ($forceLimit !== null && $forceLimit === 0)) {
            return [];
        }

        $files        = Storage::disk('public')->allFiles('demo_assets');
        $matchedFiles = [];

        foreach ($files as $file) {
            $filename  = basename($file);
            $lowerName = mb_strtolower($filename);
            $ext       = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue;
            }

            if ($categoryKey === 'k_images') {
                if (preg_match('/^k\d+\./i', $filename)) {
                    $matchedFiles[] = $file;
                }
            } elseif ($categoryKey === 'arabic_text') {
                if (str_contains($lowerName, 'تستحقين') || str_contains($lowerName, 'جلسة') || str_contains($lowerName, 'تليق')) {
                    $matchedFiles[] = $file;
                }
            } elseif ($categoryKey === 'how_sweet_shots') {
                if (str_contains($lowerName, 'قديش') || str_contains($lowerName, 'حلوين') || str_contains($lowerName, '_shots_') || str_contains($lowerName, 'explorep')) {
                    $matchedFiles[] = $file;
                }
            } elseif ($categoryKey === 'kafr_sousse_selected') {
                if (str_starts_with($lowerName, 'fb_img_')) {
                    $matchedFiles[] = $file;
                }
            } elseif ($categoryKey === 'k6_image') {
                if (preg_match('/^p2\./i', $filename)) {
                    $matchedFiles[] = $file;
                }
            } elseif ($categoryKey === 'k7_image') {
                if (preg_match('/^p3\./i', $filename)) {
                    $matchedFiles[] = $file;
                }

            }
        }

        if ($forceLimit !== null && $forceLimit > 0) {
            $matchedFiles = array_slice($matchedFiles, 0, $forceLimit);
        }

        $savedPaths = [];
        foreach ($matchedFiles as $originalPath) {
            if (Storage::disk('public')->exists($originalPath)) {
                $extension   = pathinfo($originalPath, PATHINFO_EXTENSION);
                $newFilename = 'services/images/' . Str::uuid() . '.' . $extension;
                $fileContent = Storage::disk('public')->get($originalPath);
                Storage::disk('public')->put($newFilename, $fileContent);
                $savedPaths[] = $newFilename;
            }
        }

        return $savedPaths;
    }

    private function getSpecificVideoFile(string $videoType): ?string
    {
        $files        = Storage::disk('public')->allFiles('demo_assets');
        $selectedFile = null;

        foreach ($files as $file) {
            $filename  = basename($file);
            $lowerName = mb_strtolower($filename);
            $ext       = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                continue;
            }

            // مطابقة الفيديو الذي بدون رقم (مثال: sample_video2m.mp4 لا يحتوي على أرقام 1 أو 7)
            if ($videoType === 'no_number') {
                if (! preg_match('/[17]/', $filename)) {
                    $selectedFile = $file;
                    break;
                }
            }
            // مطابقة فيديو رقم 1 (مع دعم اللاحقة m مثل sample_video1m.mp4)
            elseif ($videoType === 'video_1') {
                if (str_contains($lowerName, 'sample_video1') || str_contains($lowerName, '_1')) {
                    $selectedFile = $file;
                    break;
                }
            }

            if ($videoType === 'video_2') {
                if (str_contains($lowerName, 'sample_video2') || str_contains($lowerName, '_2')) {
                    $selectedFile = $file;
                    break;
                }
            }
            // مطابقة فيديو رقم 7 (مع دعم اللاحقة m مثل sample_video7m.mp4)
            elseif ($videoType === 'video_7') {
                if (str_contains($lowerName, 'sample_video7') || str_contains($lowerName, '_7')) {
                    $selectedFile = $file;
                    break;
                }
            }
        }

        if ($selectedFile && Storage::disk('public')->exists($selectedFile)) {
            $extension   = pathinfo($selectedFile, PATHINFO_EXTENSION);
            $newFilename = 'services/videos/' . Str::uuid() . '.' . $extension;
            $fileContent = Storage::disk('public')->get($selectedFile);
            Storage::disk('public')->put($newFilename, $fileContent);
            return $newFilename;
        }

        return null;
    }

    // ==================================================================
    // باقي أنواع الخدمات (hall, music, public_event) - بدون أي تغيير
    // ==================================================================

    private function createProviderService(int $providerId, int $cityId, string $serviceType, int $index, ?string $forcedName = null): void
    {
        DB::transaction(function () use ($providerId, $cityId, $serviceType, $index, $forcedName) {

            $names = [
                'cake'         => 'كيك مناسبات',
                'hall'         => 'قاعة أفراح',
                'music'        => 'فرقة موسيقية',
                'public_event' => 'فعالية عامة',
            ];

            $serviceName = '';
            if ($forcedName) {
                $serviceName = $forcedName;
            } elseif ($serviceType === 'hall') {
                if ($index === 1) {
                    $serviceName = 'صالة بلازا';
                } elseif ($index === 2) {
                    $serviceName = 'صالة النور';
                } else {
                    $hallOptions = ['صالة الأوسكار', 'صالة النفرتيتي', 'صالة الأزورد', 'صالة لونا بلاس'];
                    $serviceName = $hallOptions[array_rand($hallOptions)];
                }
            } else {
                $serviceName = ($names[$serviceType] ?? $serviceType) . " #{$index}";
            }

            // ✅ إحداثيات دقيقة حسب المدينة الفعلية (city_id) بدل الإحداثيات
            // الثابتة حوالين دمشق يلي كانت مستخدمة قبل
            $coords = $this->randomCoordinatesFor($this->cityNameFromId($cityId));

            $addressName = $this->publicEventAddresses[$serviceName] ?? "عنوان تجريبي - {$serviceName}";
            $finalCoords = $this->publicEventCoordinates[$serviceName] ?? $coords;

            $location = Location::create([
                'city_id'      => $cityId,
                'address_name' => $addressName,
                'latitude'     => $finalCoords['lat'],
                'longitude'    => $finalCoords['lng'],
            ]);

            $baseService = Service::firstOrCreate(
                ['service_type' => $serviceType],
                ['name' => $names[$serviceType] ?? $serviceType]
            );

            $workingDays  = $this->workingDaysOptions[array_rand($this->workingDaysOptions)];
            $workingHours = $this->workingHoursOptions[array_rand($this->workingHoursOptions)];

            [$specificDetails, $finalPrice] = match ($serviceType) {
                'cake' => [[
                    'working_days'  => $workingDays,
                    'working_hours' => $workingHours,
                    'colors'        => $this->randomSubset($this->cakeColorsPool, 2, 4),
                    'filling'       => $this->randomSubset($this->cakeFillingPool, 1, 3),
                    'flavor'        => $this->randomSubset($this->cakeFlavorPool, 1, 2),
                    'quantity'      => mt_rand(10, 50),
                    'description'   => "كيك {$serviceName} مصنوع بمكونات طازجة، مناسب لكل المناسبات الخاصة والعامة.",
                    'delivery_type' => $this->deliveryTypeOptions[array_rand($this->deliveryTypeOptions)],
                ], mt_rand(30, 150)],

                'hall'         => (function () {
                    $hasFacilities = (bool) mt_rand(0, 1);
                    $pricePerHour  = mt_rand(40, 200);
                    return [[
                        'event_types'    => $this->randomSubset($this->hallEventTypesPool, 3, 4),
                        'capacity'       => mt_rand(100, 500),
                        'price_per_hour' => $pricePerHour,
                        'has_facilities' => $hasFacilities,
                        'facilities'     => $hasFacilities ? $this->randomSubset($this->hallFacilitiesPool, 7, 10) : [],
                    ], $pricePerHour];
                })(),

                'music'        => (function () {
                    $pricePerHour = mt_rand(50, 250);
                    return [[
                        'music_types'    => $this->randomSubset($this->musicTypesPool, 1, 3),
                        'band_type'      => 'male',
                        'price_per_hour' => $pricePerHour,
                    ], $pricePerHour];
                })(),

                'public_event' => (function () use ($index) {
                    $isFree      = $index % 3 === 0;
                    $ticketPrice = $isFree ? null : mt_rand(5, 60);
                    return [[
                        'event_date'   => now()->addDays(mt_rand(5, 60))->toDateString(),
                        'start_time'   => sprintf('%02d:00', mt_rand(16, 20)),
                        'end_time'     => sprintf('%02d:00', mt_rand(21, 23)),
                        'is_free'      => $isFree,
                        'ticket_price' => $ticketPrice,
                        'ticket_count' => mt_rand(100, 500),
                    ], $isFree ? 0 : $ticketPrice];
                })(),

                default        => [['note' => 'بيانات تجريبية'], mt_rand(50, 300)],
            };

            $videoPath = null;
            if ($serviceType === 'hall') {
                $videoPath = $this->getHallSpecificVideo($serviceName);
            } elseif ($serviceType === 'public_event') {
                $videoPath = $this->getPublicEventSpecificVideo($serviceName);
            }

            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'name'        => $serviceName,
                'service_id'  => $baseService->id,
                'location_id' => $location->id,
                'price'       => $finalPrice,
                'description' => json_encode($specificDetails, JSON_UNESCAPED_UNICODE),
                'video_path'  => $videoPath,
                'status'      => 'active',
            ]);

            $savedImagesMap = [];

            if ($serviceType === 'public_event') {
                $eventImages = $this->getPublicEventSpecificImages($serviceName);
                $imagesCount = count($eventImages);

                if ($imagesCount > 0) {
                    foreach ($eventImages as $imgIndex => $imgStoragePath) {
                        $serviceImage = ServiceImage::create([
                            'provider_service_id' => $providerService->id,
                            'image_path'          => $imgStoragePath,
                            'price'               => null,
                            'title'               => "صورة {$serviceName} " . ($imgIndex + 1),
                        ]);
                        $savedImagesMap[$imgIndex] = $serviceImage->id;
                    }
                } else {
                    $imagePath    = $this->getAssetFromLocalFolder('images', 'services/images');
                    $serviceImage = ServiceImage::create([
                        'provider_service_id' => $providerService->id,
                        'image_path'          => $imagePath,
                        'price'               => null,
                        'title'               => "صورة {$serviceName}",
                    ]);
                    $savedImagesMap[0] = $serviceImage->id;
                    $imagesCount       = 1;
                }
            } else {
                $isPricedImages = $serviceType === 'cake';
                $imagesCount    = $isPricedImages ? 4 : 3;

                $tiers = [
                    ['label' => 'أساسية', 'multiplier' => 1.0, 'features' => ['خدمة أساسية', 'دعم فني عادي']],
                    ['label' => 'قياسية', 'multiplier' => 1.5, 'features' => ['كل مميزات الأساسية', 'إضافات متوسطة', 'أولوية بالحجز']],
                    ['label' => 'مميزة', 'multiplier' => 2.2, 'features' => ['كل المميزات السابقة', 'خدمة VIP', 'دعم فني على مدار الساعة']],
                ];

                foreach ($tiers as $tier) {
                    $packagePrice       = round(max($finalPrice, 20) * $tier['multiplier'], 2);
                    $packageDescription = null;

                    if ($serviceType === 'cake') {
                        $packageDescription = "باقة {$tier['label']} - {$serviceName}: " . implode('، ', $tier['features']);
                    }

                    $package = Package::create([
                        'user_id' => $providerId,
                        'name'    => "باقة {$tier['label']} - {$serviceName}",
                        'price' => $packagePrice,
                        'description' => $packageDescription,
                        'status' => 'active',
                    ]);

                    DB::table('package_services')->insert([
                        'provider_service_id' => $providerService->id,
                        'package_id'          => $package->id,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    if (! in_array($serviceType, ['photographer', 'cake'])) {
                        $featureIds = Feature::where('service_type', $serviceType)
                            ->inRandomOrder()
                            ->limit(count($tier['features']))
                            ->pluck('id');

                        foreach ($featureIds as $featureId) {
                            DB::table('package_features')->insert([
                                'package_id' => $package->id,
                                'feature_id' => $featureId,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    if (! empty($savedImagesMap) && ($serviceType === 'cake' || $serviceType === 'public_event')) {
                        $subsetImages = array_slice($savedImagesMap, 0, min(2, count($savedImagesMap)), true);
                        foreach ($subsetImages as $imgId) {
                            DB::table('package_images')->insert([
                                'package_id'       => $package->id,
                                'service_image_id' => $imgId,
                                'created_at'       => now(),
                                'updated_at'       => now(),
                            ]);
                        }
                    }
                }

                $videoNote = $videoPath ? 'مع فيديو' : 'بدون فيديو';
                $this->command->info("  ↳ {$serviceName} ({$serviceType}): {$imagesCount} صور، 3 باقات، {$videoNote}.");
            }
        });
    }

    private function getHallSpecificVideo(
        string $hallName,
        string $videoIdentifier
    ): ?string {
        $files = Storage::disk('public')->allFiles('demo_assets');

        $selectedFile = null;

        foreach ($files as $file) {
            $filename  = basename($file);
            $lowerName = mb_strtolower($filename);

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                continue;
            }

            // ----------------------------------------------------
            // فيديو بدون رقم - خاص بصالة لونا
            // sample_video.mp4
            // ----------------------------------------------------
         if ($videoIdentifier === 'no_number') {
    // ✅ بدل regex صارم بـ $ بآخره (كان بينكسر بسبب الامتداد المزدوج
    // .mp4.mp4 الموجود بأسماء الملفات الفعلية)، صرنا نتأكد إنه الاسم
    // يبدأ بـ "sample_video" ومباشرة بعدها نقطة (يعني بدون رقم أصلاً)
    if (preg_match('/^sample_video\./i', $filename) && ! preg_match('/^sample_video\d/i', $filename)) {
        $selectedFile = $file;
        break;
    }
}
elseif (is_numeric($videoIdentifier)) {
    // ✅ نفس الفكرة: نتأكد إنه الاسم يبدأ بـ sample_video{الرقم} متبوع
    // مباشرة بنقطة أو حرف/شرطة (مو رقم تاني)، بدل ما نفرض نهاية دقيقة
    // للسلسلة بعد الامتداد
    $pattern = '/^sample_video'
        . preg_quote($videoIdentifier, '/')
        . '(?![0-9])/i';

    if (preg_match($pattern, $filename) && in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
        $selectedFile = $file;
        break;
    }
}
        }

        if (! $selectedFile) {
            $this->command->warn(
                "⚠ لم يتم العثور على فيديو للصالة: {$hallName} - المطلوب: sample_video{$videoIdentifier}"
            );

            return null;
        }

        if (! Storage::disk('public')->exists($selectedFile)) {
            return null;
        }

        $extension = pathinfo($selectedFile, PATHINFO_EXTENSION);

        $newFilename =
        'services/videos/' .
        Str::uuid() .
            '.' .
            $extension;

        $fileContent = Storage::disk('public')->get($selectedFile);

        Storage::disk('public')->put(
            $newFilename,
            $fileContent
        );

        $this->command->info(
            "    🎥 {$hallName} ← {$filename}"
        );

        return $newFilename;
    }
    private function getHallSpecificImages(string $hallNameOrPrefix): array
    {
        $prefix = match ($hallNameOrPrefix) {
            'صالة النفرتيتي', 'nf'   => 'nf',
            'صالة الأوسكار', 'oscar' => 'oscar',
            'صالة التيسير', 'tayser' => 'tayser',
            'صالة الأزورد', 'lazord' => 'lazord',
            'صالة النور', 'nor'      => 'nor',
            'صالة بلازا', 'plaze'    => 'plaze',
            'صالة لونا', 'lona'      => 'lona',
            default => null,
        };

        if ($prefix === null) {
            return [];
        }

        $matchedFiles = $this->getDecorationFilesByPrefix(
            $prefix,
            ['jpg', 'jpeg', 'png', 'webp']
        );

        $savedPaths = [];

        foreach ($matchedFiles as $originalPath) {
            $newPath = $this->copyDemoAsset(
                $originalPath,
                'services/images'
            );

            if ($newPath) {
                $savedPaths[] = $newPath;
            }
        }

        return $savedPaths;
    }

    private function getPublicEventSpecificVideo(string $eventName): ?string
    {
        // الفيديو مخصص فقط لـ "نهائي دوري رواد المساجد" و "مهرجان صيف قلعة دمشق" حسب الملفات
        $files        = Storage::disk('public')->allFiles('demo_assets');
        $selectedFile = null;

        foreach ($files as $file) {
            $lowerFile = mb_strtolower($file);
            $ext       = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'mov', 'avi', 'mkv'])) {
                if ($eventName === 'نهائي دوري رواد المساجد' && (str_contains($lowerFile, 'رواد') || str_contains($lowerFile, 'المساجد') || str_contains($lowerFile, 'دوري'))) {
                    $selectedFile = $file;
                    break;
                } elseif ($eventName === 'مهرجان صيف قلعة دمشق' && (str_contains($lowerFile, 'صيف') || str_contains($lowerFile, 'قلعة'))) {
                    $selectedFile = $file;
                    break;
                }
            }
        }

        if (! $selectedFile) {
            return null;
        }

        if (Storage::disk('public')->exists($selectedFile)) {
            $extension   = pathinfo($selectedFile, PATHINFO_EXTENSION);
            $newFilename = 'services/videos/' . Str::uuid() . '.' . $extension;
            $fileContent = Storage::disk('public')->get($selectedFile);
            Storage::disk('public')->put($newFilename, $fileContent);
            return $newFilename;
        }

        return null;
    }

    private function getPublicEventSpecificImages(string $eventName): array
    {
        $files        = Storage::disk('public')->allFiles('demo_assets');
        $matchedFiles = [];

        foreach ($files as $file) {
            $lowerFile = mb_strtolower($file);
            $ext       = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            // استبعاد الفيديوهات من جلب الصور
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue;
            }

            if ($eventName === 'نهائي دوري رواد المساجد') {
                if (str_contains($lowerFile, 'رواد') || str_contains($lowerFile, 'المساجد') || str_contains($lowerFile, 'دوري')) {
                    $matchedFiles[] = $file;
                }
            } elseif ($eventName === 'معرض الكتاب') {
                if (str_contains($lowerFile, 'معرض بغداد') || str_contains($lowerFile, 'بغداد') || str_contains($lowerFile, 'j9')) {
                    $matchedFiles[] = $file;
                }
            } elseif ($eventName === 'من معرض دمشق الدولي للكتاب') {
                if (str_contains($lowerFile, 'من معرض دمشق') || (str_contains($lowerFile, 'دمشق') && str_contains($lowerFile, 'الكتاب'))) {
                    $matchedFiles[] = $file;
                }
            } elseif ($eventName === 'مهرجان صيف قلعة دمشق') {
                if (str_contains($lowerFile, 'مهرجان صيف') || str_contains($lowerFile, 'قلعة')) {
                    $matchedFiles[] = $file;
                }
            }
        }

        $savedPaths = [];
        foreach ($matchedFiles as $originalPath) {
            if (Storage::disk('public')->exists($originalPath)) {
                $extension   = pathinfo($originalPath, PATHINFO_EXTENSION);
                $newFilename = 'services/images/' . Str::uuid() . '.' . $extension;
                $fileContent = Storage::disk('public')->get($originalPath);
                Storage::disk('public')->put($newFilename, $fileContent);
                $savedPaths[] = $newFilename;
            }
        }

        return $savedPaths;
    }

    private function seedBookingsAndReviews(): void
    {
        $normalUsers      = User::where('role', 'user')->get();
        $providerServices = ProviderService::all();

        if ($normalUsers->isEmpty() || $providerServices->isEmpty()) {
            return;
        }

        foreach ($normalUsers as $user) {
            DB::transaction(function () use ($user, $providerServices) {
                $event = Event::create([
                    'user_id' => $user->id,
                    'name'    => "فعالية {$user->username} - مناسبة سابقة",
                    'budget'       => mt_rand(300, 2000),
                    'event_date'   => now()->subDays(mt_rand(10, 90)),
                    'start_time'   => '18:00:00',
                    'end_time'     => '23:00:00',
                    'status'       => 'completed',
                    'type'         => 'event',
                    'total_amount' => 0,
                ]);

                $howMany        = min(mt_rand(2, 4), $providerServices->count());
                $bookedServices = $providerServices->random($howMany);
                if (! $bookedServices instanceof \Illuminate\Support\Collection) {
                    $bookedServices = collect([$bookedServices]);
                }

                $totalAmount = 0;

                foreach ($bookedServices as $service) {
                    $eventItem = EventItem::create([
                        'event_id'            => $event->id,
                        'provider_service_id' => $service->id,
                        'package_id'          => null,
                        'start_time'          => '18:00:00',
                        'end_time'            => '20:00:00',
                        'price'               => $service->price,
                        'payment_status'      => 'paid',
                        'status'              => 'completed',
                        'quantity'            => 1,
                    ]);

                    $totalAmount += (float) $service->price;

                    $rating       = mt_rand(3, 5);
                    $commentsPool = $this->reviewCommentsByRating[$rating] ?? $this->reviewCommentsByRating[4];

                    Review::create([
                        'user_id'             => $user->id,
                        'provider_service_id' => $service->id,
                        'event_item_id'       => $eventItem->id,
                        'rating'              => $rating,
                        'comment'             => $commentsPool[array_rand($commentsPool)],
                    ]);
                }

                $event->update(['total_amount' => $totalAmount]);
            });
        }
    }

    /**
     * منطق إضافي (بدون حذف أي شي من فوق): بيمر على كل خدمات المزودين
     * الموجودة بالتطبيق، وأي خدمة ما إلها ولا تقييم لهلق (لأنو الحجوزات
     * فوق عشوائية وممكن تفوت خدمات بدون تقييم)، بيعملها حجز وهمي وتقييم
     * حتى نضمن إنو كل خدمة بالتطبيق إلها تقييم وتعليق واحد ع الأقل.
     */
    private function ensureAllServicesHaveReviews(): void
    {
        $normalUsers = User::where('role', 'user')->get();
        if ($normalUsers->isEmpty()) {
            return;
        }

        $reviewedServiceIds     = Review::pluck('provider_service_id')->unique();
        $servicesWithoutReviews = ProviderService::whereNotIn('id', $reviewedServiceIds)->get();

        if ($servicesWithoutReviews->isEmpty()) {
            $this->command->info('✔ كل الخدمات كانت أصلاً إلها تقييم واحد على الأقل.');
            return;
        }

        foreach ($servicesWithoutReviews as $service) {
            DB::transaction(function () use ($service, $normalUsers) {
                $user = $normalUsers->random();

                $event = Event::create([
                    'user_id' => $user->id,
                    'name'    => "فعالية {$user->username} - {$service->name}",
                    'budget'       => mt_rand(300, 2000),
                    'event_date'   => now()->subDays(mt_rand(10, 90)),
                    'start_time'   => '18:00:00',
                    'end_time'     => '23:00:00',
                    'status'       => 'completed',
                    'type'         => 'event',
                    'total_amount' => $service->price,
                ]);

                $eventItem = EventItem::create([
                    'event_id'            => $event->id,
                    'provider_service_id' => $service->id,
                    'package_id'          => null,
                    'start_time'          => '18:00:00',
                    'end_time'            => '20:00:00',
                    'price'               => $service->price,
                    'payment_status'      => 'paid',
                    'status'              => 'completed',
                    'quantity'            => 1,
                ]);

                $rating       = mt_rand(3, 5);
                $commentsPool = $this->reviewCommentsByRating[$rating] ?? $this->reviewCommentsByRating[4];

                Review::create([
                    'user_id'             => $user->id,
                    'provider_service_id' => $service->id,
                    'event_item_id'       => $eventItem->id,
                    'rating'              => $rating,
                    'comment'             => $commentsPool[array_rand($commentsPool)],
                ]);
            });
        }

        $this->command->info("✔ تمت تغطية " . $servicesWithoutReviews->count() . " خدمة إضافية كانت بدون تقييم، وهلق كل الخدمات إلها تقييم على الأقل.");
    }

    private function ensureFeaturesExist(): void
    {
        if (Feature::count() > 0) {
            return;
        }

        $featuresByType = [
            'decoration'   => [' عروضات', 'استشارة شخصية', 'تعديلات غير محدودة', 'إضاءة شاملة'],
            'cake'         => ['تصميم مخصص', 'طبقات إضافية', 'تغليف فاخر', 'توصيل مجاني'],
            'hall'         => ['مرايا باسماء العرسان ', 'إضاءة احترافية', 'برومو مجانا ', 'خدمة ضيافة فاخرة'],
            'photographer' => ['جلسة تصوير مسبقة', 'ألبوم مطبوع', 'تعديل احترافي', 'نسخة رقمية كاملة'],
            'music'        => ['أعضاء إضافيين', 'قائمة أغاني مخصصة', 'معدات صوت متقدمة', 'إضاءة مسرح'],
            'public_event' => ['مقاعد VIP', 'مواقف مخصصة', 'بوفيه', 'استقبال خاص'],
        ];

        foreach ($featuresByType as $type => $names) {
            foreach ($names as $name) {
                Feature::firstOrCreate(['name' => $name, 'service_type' => $type]);
            }
        }
    }

    private function resolveCities()
    {
        $cities = City::all();
        if ($cities->isEmpty()) {
            foreach (['دمشق', 'حلب', 'حمص', 'اللاذقية', 'إدلب', 'كفرسوسة'] as $name) {
                City::create(['name' => $name]);
            }
            $cities = City::all();
        }
        return $cities;
    }

    private function randomSubset(array $pool, int $min, int $max): array
    {
        $count = mt_rand($min, min($max, count($pool)));
        if ($count <= 0) {
            return [];
        }
        $keys = array_rand($pool, $count);
        $keys = is_array($keys) ? $keys : [$keys];
        return array_values(array_intersect_key($pool, array_flip($keys)));
    }

    private function getAssetFromLocalFolder(string $type, string $targetFolder): ?string
    {
        $files = Storage::disk('public')->allFiles('demo_assets');

        $filteredFiles = array_filter($files, function ($file) use ($type) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($type === 'videos') {
                return in_array($ext, ['mp4', 'mov', 'avi', 'mkv']);
            } else {
                return in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
            }
        });

        if (! empty($filteredFiles)) {
            $filteredFiles = array_values($filteredFiles);
            $randomFile    = $filteredFiles[array_rand($filteredFiles)];
            $extension     = pathinfo($randomFile, PATHINFO_EXTENSION);

            $newFilename = $targetFolder . '/' . Str::uuid() . '.' . $extension;

            if (Storage::disk('public')->exists($randomFile)) {
                $fileContent = Storage::disk('public')->get($randomFile);
                Storage::disk('public')->put($newFilename, $fileContent);
                return $newFilename;
            }
        }

        if ($type === 'images') {
            return $this->generateFallbackDummyImage($targetFolder);
        }

        return null;
    }

    private function generateFallbackDummyImage(string $folder): string
    {
        $width   = 800;
        $height  = 600;
        $image   = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 100, 100, 100);
        imagefill($image, 0, 0, $bgColor);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        imagestring($image, 5, 20, 20, 'DEMO IMAGE', $textColor);

        ob_start();
        imagejpeg($image, null, 85);
        $imageData = ob_get_clean();
        imagedestroy($image);

        $filename = $folder . '/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($filename, $imageData);

        return $filename;
    }
    private function createHallService(
        int $providerId,
        int $cityId,
        array $group,
        int $index
    ): void {
        DB::transaction(function () use ($providerId, $cityId, $group, $index) {

            $baseService = Service::firstOrCreate(
                ['service_type' => 'hall'],
                ['name' => 'قاعة أفراح']
            );

            // ✅ إحداثيات دقيقة حسب المدينة الفعلية (city_id) بدل الإحداثيات
            // الثابتة حوالين دمشق يلي كانت مستخدمة قبل
            $coords = $this->randomCoordinatesFor($this->cityNameFromId($cityId));

            $location = Location::create([
                'city_id'      => $cityId,
                'address_name' => $group['address'] ?? "عنوان تجريبي - {$group['name']}",
                'latitude'  => $coords['lat'],
                'longitude' => $coords['lng'],
            ]);

            $workingDays = $this->workingDaysOptions[
                array_rand($this->workingDaysOptions)
            ];

            $workingHours = $this->workingHoursOptions[
                array_rand($this->workingHoursOptions)
            ];

            $pricePerHour = mt_rand(40, 200);

            $specificDetails = [
                'working_days'   => $workingDays,
                'working_hours'  => $workingHours,
                'event_types'    => $this->randomSubset(
                    $this->hallEventTypesPool,
                    3,
                    4,
                ),
                'capacity'       => mt_rand(100, 500),
                'price_per_hour' => $pricePerHour,
                'has_facilities' => true,
                'facilities'     => $this->randomSubset(
                    $this->hallFacilitiesPool,
                    7,
                    10,
                ),
            ];

            // الفيديو الخاص بالصالة
            $videoPath = $this->getHallSpecificVideo(
                $group['name'],
                $group['video']
            );

            $providerService = ProviderService::create([
                'user_id'     => $providerId,
                'name'        => $group['name'],
                'service_id'  => $baseService->id,
                'location_id' => $location->id,
                'price'       => $pricePerHour,
                'description' => json_encode(
                    $specificDetails,
                    JSON_UNESCAPED_UNICODE
                ),
                'video_path'  => $videoPath,
                'status'      => 'active',
            ]);

            // الصور الخاصة بالصالة
            $imagePaths = $this->getHallSpecificImages(
                $group['image_prefix']
            );

            $savedImagesMap = [];

            foreach ($imagePaths as $imgIndex => $imgStoragePath) {

                $serviceImage = ServiceImage::create([
                    'provider_service_id' => $providerService->id,
                    'image_path'          => $imgStoragePath,
                    'price'               => null,
                    'title'               => "صورة {$group['name']} " . ($imgIndex + 1),
                ]);

                $savedImagesMap[$imgIndex] = $serviceImage->id;
            }

            // الباقات
            $tiers = [
                [
                    'label'      => 'أساسية',
                    'multiplier' => 1.0,
                    'features'   => [
                        'خدمة أساسية',
                        'دعم فني عادي',
                    ],
                ],
                [
                    'label'      => 'قياسية',
                    'multiplier' => 1.5,
                    'features'   => [
                        'كل مميزات الأساسية',
                        'إضافات متوسطة',
                        'أولوية بالحجز',
                    ],
                ],
                [
                    'label'      => 'مميزة',
                    'multiplier' => 2.2,
                    'features'   => [
                        'كل المميزات السابقة',
                        'خدمة VIP',
                        'دعم فني على مدار الساعة',
                    ],
                ],
            ];

            foreach ($tiers as $tier) {

                $packagePrice = round(
                    max($pricePerHour, 20) * $tier['multiplier'],
                    2
                );

                $package = Package::create([
                    'user_id' => $providerId,
                    'name'    => "باقة {$tier['label']} - {$group['name']}",
                    'price'       => $packagePrice,
                    'description' => "باقة {$tier['label']} - {$group['name']}: " . implode('، ', $tier['features']),
                    'status'      => 'active',
                ]);

                DB::table('package_services')->insert([
                    'provider_service_id' => $providerService->id,
                    'package_id'          => $package->id,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $featureIds = Feature::where('service_type', 'hall')
                    ->inRandomOrder()
                    ->limit(count($tier['features']))
                    ->pluck('id');

                foreach ($featureIds as $featureId) {

                    DB::table('package_features')->insert([
                        'package_id' => $package->id,
                        'feature_id' => $featureId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (! empty($savedImagesMap)) {

                    $subsetImages = array_slice(
                        $savedImagesMap,
                        0,
                        min(2, count($savedImagesMap)),
                        true
                    );

                    foreach ($subsetImages as $imgId) {

                        DB::table('package_images')->insert([
                            'package_id'       => $package->id,
                            'service_image_id' => $imgId,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]);
                    }
                }
            }

            $this->command->info(
                "  ↳ {$group['name']}: "
                . count($imagePaths)
                . " صور، 3 باقات، "
                . ($videoPath ? 'مع فيديو' : 'بدون فيديو')
            );
        });
    }

}
