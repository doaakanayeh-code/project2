<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactMessageController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        try {
            $contactMessage = ContactMessage::create($validated);

            // إرسال إيميل للأدمن
            try {
                Mail::send('emails.admin_notification', ['msg' => $contactMessage], function ($message) use ($contactMessage) {
                    $message->to(config('mail.from.address')) // يفضل استخدام config
                            ->subject('رسالة تواصل جديدة من: ' . $contactMessage->name)
                            ->replyTo($contactMessage->email, $contactMessage->name);
                });
            } catch (\Exception $e) {
                Log::error('[CONTACT STORE MAIL ERROR] ' . $e->getMessage());
            }

            return response()->json([
                'status'  => true,
                'message' => __('messages.contact_sent_success')
            ], 201);

        } catch (\Exception $e) {
            Log::error('[CONTACT STORE ERROR] ' . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => __('messages.general_error')
            ], 500);
        }
    }

    public function reply(Request $request, $id)
    {
        $request->validate(['admin_reply' => 'required|string']);

        try {
            $contactMessage = ContactMessage::findOrFail($id);

            $contactMessage->update([
                'admin_reply' => $request->admin_reply,
                'status'      => 'replied',
                'is_read'     => true
            ]);

            Mail::raw($request->admin_reply, function ($message) use ($contactMessage) {
                $message->to($contactMessage->email)
                        ->subject('رد من إدارة Royal Moments');
            });

            return response()->json([
                'status'  => true,
                'message' => __('messages.reply_sent_success')
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => __('messages.message_not_found')], 404);
        } catch (\Exception $e) {
            Log::error('[REPLY ERROR] ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => __('messages.email_failed') . $e->getMessage()], 500);
        }
    }

    public function deleteReply($id)
    {
        try {
            $contactMessage = ContactMessage::findOrFail($id);

            $contactMessage->update([
                'admin_reply' => null,
                'status'      => 'pending',
                'is_read'     => false
            ]);

            return response()->json([
                'status'  => true,
                'message' => __('messages.reply_deleted_success')
            ], 200);

        } catch (\Exception $e) {
            Log::error('[DELETE REPLY ERROR] ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => __('messages.general_error')], 500);
        }
    }

    public function updateReply(Request $request, $id)
    {
        $request->validate(['admin_reply' => 'required|string']);

        try {
            $contactMessage = ContactMessage::findOrFail($id);

            $contactMessage->update([
                'admin_reply' => $request->admin_reply
            ]);

            Mail::raw('عذراً، قمنا بتحديث ردنا السابق ليصبح: ' . "\n\n" . $request->admin_reply, function ($message) use ($contactMessage) {
                $message->to($contactMessage->email)
                        ->subject('تحديث للرد من إدارة Royal Moments');
            });

            return response()->json([
                'status'  => true,
                'message' => __('messages.reply_updated_success')
            ], 200);

        } catch (\Exception $e) {
            Log::error('[UPDATE REPLY ERROR] ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => __('messages.update_db_failed_email')], 500);
        }
    }
}