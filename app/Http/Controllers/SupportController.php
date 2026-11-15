<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => 'required|uuid',
            'type' => ['required', Rule::in(['player', 'support'])],
            'reported_user_id' => 'nullable|integer|exists:users,id',
            'player_name' => 'required_if:type,player|nullable|string|max:120',
            'post_id' => 'nullable|integer|exists:bleeter_posts,id',
            'reason' => ['required_if:type,player', 'nullable', Rule::in(['harassment', 'hate', 'spam', 'cheating', 'other'])],
            'reply_to' => 'nullable|email:rfc|max:191',
            'subject' => 'required|string|min:3|max:140',
            'message' => 'required|string|min:10|max:5000',
        ]);
        $user = $request->user();
        $existing = DB::table('support_reports')->where('request_id', $data['request_id'])->first();
        if ($existing && (int) $existing->user_id !== $user->id) {
            abort(409, 'Request ID already used.');
        }
        if ($existing?->mail_sent_at) {
            return response()->json(['sent' => true, 'reference' => $existing->id]);
        }

        $id = $existing?->id ?? DB::table('support_reports')->insertGetId([
            ...$data, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $report = DB::table('support_reports')->where('id', $id)->first();
        $body = "Reference: {$id}\nType: {$report->type}\nSender: {$user->name} (user {$user->id})\n"
            ."Player: {$report->player_name}\nPlayer ID: {$report->reported_user_id}\nBleet: {$report->post_id}\n"
            ."Reason: {$report->reason}\nReply to: {$report->reply_to}\n\n{$report->message}";
        try {
            Mail::mailer(config('support.mailer'))->raw($body, function ($mail) use ($report, $id) {
                $mail->to(config('support.email'))->from(config('support.from'), 'GTA VI Phone')
                    ->subject("[GTA #{$id}] ".str_replace(["\r", "\n"], ' ', $report->subject));
                if ($report->reply_to) {
                    $mail->replyTo($report->reply_to);
                }
            });
            DB::table('support_reports')->where('id', $id)->update(['mail_sent_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $error) {
            report($error);

            return response()->json(['error' => "Your message was saved as #{$id}, but email delivery failed. Retry or email gta@qla.dev with that reference."], 503);
        }

        return response()->json(['sent' => true, 'reference' => $id], 201);
    }
}
