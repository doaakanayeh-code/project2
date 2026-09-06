<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

class GoogleIdentityService
{
    public function verifyId($imagePath)
    {
        try {
            $imagePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath);

            $tempFileName = 'processed_' . basename($imagePath);
            $tempPath = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $tempFileName);
            
            // بديل التدخل لمكتبة Image: استخدام مكتبة GD المدمجة في PHP لتحسين الصورة
            if (file_exists($imagePath)) {
                $info = getimagesize($imagePath);
                $mime = $info['mime'] ?? '';
                
                // إنشاء الصورة حسب نوعها
                if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                    $srcImg = imagecreatefromjpeg($imagePath);
                } elseif ($mime == 'image/png') {
                    $srcImg = imagecreatefrompng($imagePath);
                } else {
                    $srcImg = false;
                }

                if ($srcImg) {
                    // تحويل الصورة للأبيض والأسود (Greyscale) لتحسين دقة الـ OCR
                    imagefilter($srcImg, IMG_FILTER_GRAYSCALE);
                    // زيادة التباين قليلاً (Contrast) ليصبح النص أوضح للـ Tesseract
                    imagefilter($srcImg, IMG_FILTER_CONTRAST, -10); 
                    
                    // حفظ الصورة المعالجة في المجلد المؤقت
                    imagejpeg($srcImg, $tempPath, 90);
                    imagedestroy($srcImg);
                } else {
                    // إذا لم يدعم النوع، ننسخها كما هي
                    copy($imagePath, $tempPath);
                }
            } else {
                throw new \Exception("Original image not found at: " . $imagePath);
            }
                
            $tesseractFolder = 'C:/Program Files/Tesseract-OCR';
            $tesseractDataDir = $tesseractFolder . '/tessdata';

            // إجبار النظام على التعرف على مسار اللغات
            putenv("TESSDATA_PREFIX=" . $tesseractDataDir);

            $text = (new TesseractOCR($tempPath)) 
                ->executable($tesseractFolder . '/tesseract.exe')
                ->tessdataDir($tesseractDataDir)
                ->lang('ara') 
                ->psm(3) 
                ->oem(1)
                ->config('user_defined_dpi', '300')
                ->run();

            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return [
                'success' => true,
                'text'    => $this->cleanText($text),
            ];

        } catch (\Exception $e) {
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function cleanText($text)
    {
        $text = preg_replace('/[\x{200E}\x{200F}\x{200B}\x{200C}\x{200D}]/u', '', $text);
        
        $text = trim($text);
        
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        
        $text = preg_replace('/[ \t]+/u', ' ', $text);

        $text = str_replace(['أ','إ','آ'], 'ا', $text);
        $text = str_replace('ى', 'ي', $text);
        $text = str_replace('ة', 'ه', $text);

        return $text;
    }
}