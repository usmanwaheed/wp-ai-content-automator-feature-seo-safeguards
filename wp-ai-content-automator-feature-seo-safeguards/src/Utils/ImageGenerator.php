<?php
declare(strict_types=1);

namespace AICA\Utils;

class ImageGenerator
{
    private float $costCap;
    private float $spent = 0.0;
    private string $apiKey;

    public function __construct(float $costCap = 0.30)
    {
        $this->costCap = $costCap;
        $settings = get_option('aica_settings', []);
        $this->apiKey = isset($settings['openai_key']) ? (string) $settings['openai_key'] : '';
    }

    /**
     * Generate an image using DALL-E 3, with up to 3 retries
     */
    public function generate(string $prompt): ?string
    {
        if ($this->spent >= $this->costCap || empty($prompt) || empty($this->apiKey)) {
            return null;
        }

        $attempts = 0;
        $maxRetries = 3;

        while ($attempts < $maxRetries) {
            $attempts++;

            $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'prompt' => $prompt,
                    'model'  => 'dall-e-3',
                    'n'      => 1,
                    'size'   => '1024x1024',
                ]),
                'timeout' => 60,
            ]);

            if (is_wp_error($response)) {
                error_log("[AICA] Image generation error (Attempt $attempts): " . $response->get_error_message());
                continue;
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            if ($responseCode !== 200) {
                error_log("[AICA] Image API returned status $responseCode (Attempt $attempts)");
                continue;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($body) || empty($body['data'][0]['url'])) {
                error_log("[AICA] Unexpected response structure during image generation (Attempt $attempts)");
                continue;
            }

            // Success
            $this->spent += 0.08;
            return $body['data'][0]['url'];
        }

        return null;
    }

    /**
     * Get remaining budget
     */
    public function getRemainingBudget(): float
    {
        return max(0, $this->costCap - $this->spent);
    }
}
