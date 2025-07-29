<?php
namespace AICA\Domain;

final class ContentStrategy implements \JsonSerializable
{
    public bool   $enabled;
    public string $source_url;
    public string $kw_primary;
    public string $kw_secondary;
    public string $tone;
    public int    $min_words;
    public int    $max_words;
    public string $recurrence;
    public array  $weekdays;
    public string $publish_time;
    public int    $category_id;
    public int    $interval_days;
    public string $start_date;
    public int    $month_day;
    public string $stop_condition;
    public int    $max_posts;
    public string $end_date;
    public bool   $enable_images;
    public int    $image_count;

    public function __construct(array $in)
    {
        $this->enabled = isset($in['enabled']) ? (bool)$in['enabled'] : false;
        $this->source_url = esc_url_raw($in['source_url'] ?? '');
        $this->kw_primary = sanitize_text_field($in['kw_primary'] ?? '');
        $this->kw_secondary = sanitize_text_field($in['kw_secondary'] ?? '');

        // Fixed tone handling
        $tone = $in['tone'] ?? 'conversational';
        $this->tone = in_array($tone, ['professional','conversational','persuasive'], true)
                      ? $tone : 'conversational';

        if ($this->enabled && !$this->source_url) {
            throw new \InvalidArgumentException('Missing pillar URL');
        }

        $this->min_words = max(300, intval($in['min_words'] ?? 600));
        $this->max_words = max($this->min_words + 100, intval($in['max_words'] ?? 1200));

        // FIXED: recurrence handling
        $recurrence = $in['recurrence'] ?? 'daily';
        $this->recurrence = in_array($recurrence, ['daily','interval','weekly','monthly'], true)
                            ? $recurrence : 'daily';

        $this->weekdays = array_values(array_intersect(
            (array)($in['weekdays'] ?? []),
            ['mon','tue','wed','thu','fri','sat','sun']
        ));

        $this->publish_time = preg_match('/^\d{2}:\d{2}$/', $in['publish_time'] ?? '')
                              ? $in['publish_time'] : '09:00';

        $this->category_id = intval($in['category_id'] ?? get_option('default_category'));
        $this->interval_days = max(0, intval($in['interval_days'] ?? 0));

        $this->start_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['start_date'] ?? '')
                            ? $in['start_date'] : '';

        $this->month_day = min(31, max(1, intval($in['month_day'] ?? 1)));

        // FIXED: stop_condition handling
        $stopCondition = $in['stop_condition'] ?? 'none';
        $this->stop_condition = in_array($stopCondition, ['none','max_posts','end_date'], true)
                                ? $stopCondition : 'none';

        $this->max_posts = max(0, intval($in['max_posts'] ?? 0));

        $this->end_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['end_date'] ?? '')
                          ? $in['end_date'] : '';

        $this->enable_images = (bool)($in['enable_images'] ?? false);
        $this->image_count = min(6, max(1, intval($in['image_count'] ?? 3)));

        if ($this->stop_condition === 'end_date' && $this->start_date && $this->end_date) {
            $sd = \DateTime::createFromFormat('Y-m-d', $this->start_date);
            $ed = \DateTime::createFromFormat('Y-m-d', $this->end_date);
            if ($sd && $ed && $ed < $sd) {
                $this->end_date = $this->start_date;
            }
        }
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}