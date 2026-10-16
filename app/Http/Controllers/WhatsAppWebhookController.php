<?php

namespace App\Http\Controllers;

use App\Models\{WaConversation, WaMessage};
use App\Services\ConversationAssigner;
use Illuminate\Http\Request;
use Netflie\WhatsAppCloudApi\WebHook;

class WhatsAppWebhookController extends Controller
{
    // Verifikasi dari Meta
    public function verify(Request $r)
    {
        try {
            return response((new WebHook())->verify($r->query(), config('services.whatsapp.verify_token')));
        } catch (\Throwable $e) {
            abort(403);
        }
    }

    // Pesan masuk + status kirim
    public function handle(Request $r, ConversationAssigner $assigner)
    {
        $expected = 'sha256='.hash_hmac('sha256', $r->getContent(), config('services.whatsapp.app_secret'));
        abort_unless(hash_equals($expected, (string) $r->header('X-Hub-Signature-256')), 403);

        foreach ($r->input('entry', []) as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $v = $change['value'] ?? [];
                $names = collect($v['contacts'] ?? [])
                    ->mapWithKeys(fn ($c) => [($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);

                foreach ($v['messages'] ?? [] as $m) {
                    $this->storeIncoming($m, $names[$m['from'] ?? ''] ?? null, $assigner);
                }

                foreach ($v['statuses'] ?? [] as $s) {   // sent / delivered / read / failed
                    WaMessage::where('wa_message_id', $s['id'])->update(['status' => $s['status']]);
                }
            }
        }

        return response()->noContent();   // Meta butuh 200 cepat
    }

    private function storeIncoming(array $m, ?string $name, ConversationAssigner $assigner): void
    {
        if (WaMessage::where('wa_message_id', $m['id'])->exists()) return;   // retry dari Meta

        $body = $m['text']['body']
            ?? $m[$m['type']]['caption']
            ?? '['.$m['type'].']';

        $c = WaConversation::firstOrCreate(
            ['wa_id' => $m['from']],
            ['contact_name' => $name]
        );

        $c->messages()->create([
            'direction' => 'in', 'type' => $m['type'], 'body' => $body, 'wa_message_id' => $m['id'],
        ]);

        $c->update([
            'last_message'             => $body,
            'last_message_at'          => now(),
            'last_customer_message_at' => now(),
            'status'                   => $c->status === 'closed' ? ($c->assigned_to ? 'open' : 'unassigned') : $c->status,
        ]);

        if (! $c->assigned_to && config('sales.assign_mode') === 'round_robin') {
            $assigner->autoAssign($c);
        }
    }
}