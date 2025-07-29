<?php
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
        $this->apiKey = $settings['openai_key'] ?? '';
    }

    /**
     * Generate an image using DALL-E 3
     */
    public function generate(string $prompt): ?string
    {
        if ($this->spent >= $this->costCap || empty($prompt) || empty($this->apiKey)) {
            return null;
        }

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
            error_log('[AICA] Image generation error: ' . $response->get_error_message());
            return null;
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        if ($responseCode !== 200) {
            error_log('[AICA] Image API returned status: ' . $responseCode);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $imageUrl = $body['data'][0]['url'] ?? null;

        if ($imageUrl) {
            // Track cost (approximate $0.08 per DALL-E 3 image)
            $this->spent += 0.08;
        }

        return $imageUrl;
    }

    /**
     * Get remaining budget
     */
    public function getRemainingBudget(): float
    {
        return max(0, $this->costCap - $this->spent);
    }
}
