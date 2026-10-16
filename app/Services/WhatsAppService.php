<?php

namespace App\Services;

use App\Models\{User, WaConversation, WaMessage};
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class WhatsAppService
{
    public function __construct(private WhatsAppCloudApi $api) {}

    public function sendText(WaConversation $c, string $text, User $by): WaMessage
    {
        $last = $c->last_customer_message_at;
        if (! $last || $last->lt(now()->subHours(24))) {
            throw new \DomainException('Sudah lewat 24 jam. Gunakan pesan template.');
        }

        $res = $this->api->sendTextMessage($c->wa_id, $text);

        return $this->logOut($c, $text, $by, $res->decodedBody()['messages'][0]['id'] ?? null);
    }

    public function sendTemplate(WaConversation $c, string $name, string $lang, User $by): WaMessage
    {
        $res = $this->api->sendTemplate($c->wa_id, $name, $lang);

        return $this->logOut($c, "[template: $name]", $by, $res->decodedBody()['messages'][0]['id'] ?? null, 'template');
    }

    private function logOut(WaConversation $c, string $body, User $by, ?string $id, string $type = 'text'): WaMessage
    {
        $c->update(['last_message' => $body, 'last_message_at' => now()]);

        return $c->messages()->create([
            'direction' => 'out', 'type' => $type, 'body' => $body,
            'wa_message_id' => $id, 'status' => 'sent', 'sent_by' => $by->id,
        ]);
    }
}