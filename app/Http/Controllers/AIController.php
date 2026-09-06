<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedText;

class AIController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'text' => 'required|string'
        ]);

        $userText = $request->text;
        $userName = auth()->check() ? auth()->user()->name : 'عزيزي العميل';
        $replyText = $this->analyzeAndCompare($userText, $userName);
        $audioPath = '/audio/responses/dynamic_generated.mp3'; 

        SavedText::create([
            'user_speech'         => $userText,
            'ai_response'         => $replyText,
            'audio_response_path' => $audioPath,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Processed successfully',
            'reply_text' => $replyText,
            'audio_url'  => asset($audioPath) 
        ], 200);
    }

    private function analyzeAndCompare($text, $userName)
{
    // تحويل النص لحروف صغيرة وإزالة المسافات الزائدة
    $text = trim(mb_strtolower($text, 'UTF-8'));

    // 1. أولاً: فحص الكلمات المتعلقة بالأسعار (لأنها محددة جداً)
    if (str_contains($text, 'نعم') || str_contains($text, 'ياريت') || str_contains($text, 'الأسعار') || str_contains($text, 'قديش') || str_contains($text, 'السعر')) {
        return "تكرم عينك يا " . $userName . "، حجز صالة مون هاوس يبدأ من خمسمئة دولار شاملة الكوشة والضيافة الأساسية. هل تود تثبيت الحجز المبدئي وتحديد التاريخ الآن؟";
    }

    // 2. ثانياً: فحص اسم صالة محددة "مون هاوس"
    else if (str_contains($text, 'مون هاوس') || str_contains($text, 'moon house')) {
        return "اختيار موفق يا " . $userName . "! صالة مون هاوس هي خيار رائع جداً، تتسع لثلاثمئة شخص وتتميز بإطلالة ساحرة وإضاءة ليزرية متكاملة. هل ترغب في معرفة أسعار الحجز المتاحة لهذه الصالة؟";
    }

    // 3. ثالثاً: فحص العناوين والمواقع المخصصة للصالة أولاً
    else if (str_contains($text, 'أنا في دمشق') || str_contains($text, 'عنوان الصالة') || str_contains($text, 'موقع الصالة')) {
        return "تمام يا عزيزتي " . $userName . "، الصالة موقعها في دمشق ويمكنك العثور على موقعها بدقة على الخريطة وتفاصيل الاتصال كاملة في أسفل شاشة الموقع.";
    }

    // 4. رابعاً: فحص الكلمات العامة للعناوين والموقع (إذا لم تطابق الجمل المخصصة في الأعلى)
    else if (str_contains($text, 'وين') || str_contains($text, 'عنوان') || str_contains($text, 'موقع')) {
        return "مكتب رويال مومنت الرئيسي يقع في دمشق. ويمكنك العثور على الخريطة وتفاصيل الاتصال كاملة في أسفل شاشة الموقع.";
    }

    // 5. خامساً: فحص طلبات الحجز العامة
    else if (str_contains($text, 'حجز') || str_contains($text, 'صالة') || str_contains($text, 'بدي احجز')) {
        return "على الرحب والسعة يا " . $userName . "، لدينا في رويال مومنت خيارات مثل صالة مون هاوس وصالة الجلاء الملكية والصالة البهية. هل لديك اسم صالة معينة تفضلها أم تريدني أن أقترح عليك؟";
    }

    // 6. سادساً: فحص الاستفسار عن الخدمات
    else if (str_contains($text, 'الخدمات') || str_contains($text, 'شو بتقدم') || str_contains($text, 'شو في عندك')) {
        return "أهلاً بك يا " . $userName . " في رويال مومنت! نحن هنا لمساعدتك في التخطيط لمناسبتك المثالية. يمكننا مساعدتك في اختيار الصالات، والتعاقد مع المصورين والمنسقين وأيضا فرق ال Dj. ما هو نوع المناسبة التي تخطط لها اليوم؟";
    }

    // 7. الرد الافتراضي في حال لم يفهم أي كلمة مفتاحية
    else {
        return "أهلاً بك يا " . $userName . " في رويال مومنت! أنا المساعد الذكي لمشروع Royal Moments. يمكنك سؤالي عن الصالات المتاحة مثل مون هاوس، أسعار الحجز، أو مواقعنا وكيفية الحجز.";
    }
}
}