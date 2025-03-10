<?php

namespace Dps;

use Cekurte\Environment\Environment as env;

/**
 * @requires $_ENV['DISCORD_WEBHOOK_URL']
 * @author https://github.com/denchik5133/discord-webhook-sender
 */
class Discord {
    private $webhookUrl;
    private $cooldownTime = 3; // Time delay between requests in seconds
    private $lastSendTimeFile = __DIR__ . '/last_send_time.txt'; // File for storing the time of the last message sent

    /**
     * Crea un lanzador de mensajes a un canal de discord
     * @param string $webhookUrl URL del webhook entregada por discord
     */
    public function __construct( string $webhookUrl = null) {
        $this->webhookUrl = $webhookUrl ?? env::get( 'DISCORD_WEBHOOK_URL', null );
    }


    // Discord tiene un limite de 30 envios por minuto
    // Esto provoca un delay de N segundos para evitar estos limites
    private function applyCooldown() {
        if (file_exists($this->lastSendTimeFile)) {
            $lastSendTime = (int)file_get_contents($this->lastSendTimeFile);
            $currentTime  = time();

            // Calculate the remaining time until the next allowed request
            $timeSinceLastSend = $currentTime - $lastSendTime;
            if ($timeSinceLastSend < $this->cooldownTime) {
                $sleepTime = $this->cooldownTime - $timeSinceLastSend;
                sleep($sleepTime);
            }
        }

        // Update the time of the last sent message
        file_put_contents($this->lastSendTimeFile, time());
    }

    /**
     * Envia un mensaje al canal
     * @link https://discord.com/developers/docs/resources/webhook
     * @param mixed $content
     * @param mixed $embeds
     * @param mixed $username
     * @param mixed $avatarUrl
     * @throws \Exception
     * @return bool
     */
    public function sendMessage($content, $embeds = [], $username = null, $avatarUrl = null) {
        // Applying a delay between requests
        $this->applyCooldown();

        // Checking Webhook URLs
        if (empty($this->webhookUrl) || !filter_var($this->webhookUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid Webhook URL');
        }

        $payload = [
            'content'    => $content,
            'username'   => $username,
            'avatar_url' => $avatarUrl,
            'embeds'     => $embeds
        ];
       
        $payload = json_encode(array_filter($payload, function($value) {
            return $value !== null;
        }));

        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        // Disabling SSL validation for debugging
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 204) {
            // Checking response status and error handling
            error_log("[DISCORD] Failed to send message, HTTP status code: $httpCode. cURL Error: $error" );
            return false;
        } else {
            return true;
        }
    }
}

