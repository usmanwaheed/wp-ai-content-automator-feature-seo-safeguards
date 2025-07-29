<?php
// File: src/Admin/Tabs/StrategySingleTab.php
namespace AICA\Admin\Tabs;

class StrategySingleTab extends BaseTab
{
    private int $index;

    public function __construct(int $index)
    {
        $this->index = $index;
        /* Tab title: just “Strategy n” */
        parent::__construct(
            'strategy' . ($index + 1),
            'Strategy ' . ($index + 1)
        );
    }

    /*──────────────────────  Render  ──────────────────────*/
    public function render(): void
    {
        /* Defaults */
        $raw = get_option('aica_strategies', [])[$this->index] ?? [];
        $s   = wp_parse_args($raw, [
            'enabled'        => 0,
            'source_url'     => '',
            'kw_primary'     => '',
            'kw_secondary'   => '',
            'tone'           => 'conversational',
            'min_words'      => 600,
            'max_words'      => 1200,
            'publish_time'   => '09:00',
            'recurrence'     => 'daily',
            'interval_days'  => 0,
            'start_date'     => '',
            'weekdays'       => [],
            'month_day'      => 1,
            'category_id'    => get_option('default_category'),
            'stop_condition' => 'none',
            'max_posts'      => 0,
            'end_date'       => '',
            'enable_images'  => 0,
            'image_count'    => 3,
        ]);
        ?>
        <table class="form-table" role="presentation">

            <!-- ▼ Status indicator row (green / red dot) ▼ -->
            <?php
            $dotClass = $s['enabled'] ? 'aica-status-indicator aica-enabled'
                                      : 'aica-status-indicator aica-paused';
            ?>
            <tr>
                <th>Status</th>
                <td>
                    <span class="<?php echo esc_attr($dotClass); ?>"></span>
                    <?php echo $s['enabled'] ? 'Strategy Enabled' : 'Strategy Paused'; ?>
                </td>
            </tr>
            <!-- ▲ end status row ▲ -->

            <!-- Source URL -->
            <tr>
                <th>
                    <label for="src_<?php echo $this->index; ?>">Source URL&nbsp;(required)</label>
                    <span class="dashicons dashicons-editor-help"
                          title="A page the AI analyses for topics &amp; links.
Make it the hub (pillar) for this cluster."></span>
                </th>
                <td>
                    <input type="url" id="src_<?php echo $this->index; ?>"
                           name="aica_strategies[<?php echo $this->index; ?>][source_url]"
                           value="<?php echo esc_attr($s['source_url']); ?>"
                           class="regular-text">
                </td>
            </tr>

            <!-- Primary Keyword -->
            <tr>
                <th>
                    <label for="kw1_<?php echo $this->index; ?>">Primary Keyword</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Main focus term. Leave blank to let the AI decide."></span>
                </th>
                <td>
                    <input type="text" id="kw1_<?php echo $this->index; ?>"
                           name="aica_strategies[<?php echo $this->index; ?>][kw_primary]"
                           value="<?php echo esc_attr($s['kw_primary']); ?>" class="regular-text">
                </td>
            </tr>

            <!-- Secondary Keywords -->
            <tr>
                <th>
                    <label for="kw2_<?php echo $this->index; ?>">Secondary Keywords</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Comma-separated list of extra terms to include."></span>
                </th>
                <td>
                    <input type="text" id="kw2_<?php echo $this->index; ?>"
                           name="aica_strategies[<?php echo $this->index; ?>][kw_secondary]"
                           value="<?php echo esc_attr($s['kw_secondary']); ?>" class="regular-text">
                </td>
            </tr>

            <!-- Tone -->
            <tr>
                <th>
                    <label for="tone_<?php echo $this->index; ?>">Tone</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Professional · Conversational · Persuasive"></span>
                </th>
                <td>
                    <select id="tone_<?php echo $this->index; ?>"
                            name="aica_strategies[<?php echo $this->index; ?>][tone]">
                        <?php foreach ([
                            'professional'   => 'Professional',
                            'conversational' => 'Conversational',
                            'persuasive'     => 'Persuasive'
                        ] as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>"
                                <?php selected($s['tone'], $val); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Word range -->
            <tr>
                <th>
                    <label>Min&nbsp;/&nbsp;Max Words</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Draft length range. Max ≥ Min + 100."></span>
                </th>
                <td>
                    <input type="number" min="300"
                           name="aica_strategies[<?php echo $this->index; ?>][min_words]"
                           value="<?php echo esc_attr($s['min_words']); ?>" class="small-text"> &ndash;
                    <input type="number"
                           name="aica_strategies[<?php echo $this->index; ?>][max_words]"
                           value="<?php echo esc_attr($s['max_words']); ?>"
                           min="<?php echo esc_attr($s['min_words'] + 100); ?>" class="small-text"> words
                </td>
            </tr>

            <!-- Publish time -->
            <tr>
                <th>
                    <label for="time_<?php echo $this->index; ?>">Publish Time</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Server local time (HH:MM)."></span>
                </th>
                <td>
                    <input type="time" id="time_<?php echo $this->index; ?>"
                           name="aica_strategies[<?php echo $this->index; ?>][publish_time]"
                           value="<?php echo esc_attr($s['publish_time']); ?>">
                </td>
            </tr>

            <!-- Recurrence -->
            <?php $rec = $s['recurrence']; ?>
            <tr>
                <th>
                    <label>Recurrence</label>
                    <span class="dashicons dashicons-editor-help"
                          title="How often to generate a draft."></span>
                </th>
                <td>
                    <!-- Daily -->
                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][recurrence]"
                               value="daily" <?php checked($rec, 'daily'); ?>> Daily
                    </label><br><br>

                    <!-- Interval -->
                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][recurrence]"
                               value="interval" <?php checked($rec, 'interval'); ?>> Every
                    </label>
                    <input type="number" min="1" style="width:60px;"
                           name="aica_strategies[<?php echo $this->index; ?>][interval_days]"
                           value="<?php echo esc_attr($s['interval_days']); ?>"> days starting
                    <input type="date"
                           name="aica_strategies[<?php echo $this->index; ?>][start_date]"
                           value="<?php echo esc_attr($s['start_date']); ?>"><br><br>

                    <!-- Weekly -->
                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][recurrence]"
                               value="weekly" <?php checked($rec, 'weekly'); ?>> Weekly on
                    </label>
                    <?php
                    foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d) :
                        $v = strtolower($d); ?>
                        <label style="margin-left:8px;">
                            <input type="checkbox" value="<?php echo esc_attr($v); ?>"
                                   name="aica_strategies[<?php echo $this->index; ?>][weekdays][]"
                                   <?php checked(in_array($v, $s['weekdays'], true)); ?>> <?php echo esc_html($d); ?>
                        </label>
                    <?php endforeach; ?><br><br>

                    <!-- Monthly -->
                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][recurrence]"
                               value="monthly" <?php checked($rec, 'monthly'); ?>> Monthly on day
                    </label>
                    <input type="number" min="1" max="31" style="width:60px;"
                           name="aica_strategies[<?php echo $this->index; ?>][month_day]"
                           value="<?php echo esc_attr($s['month_day']); ?>">
                </td>
            </tr>

            <!-- Category -->
            <tr>
                <th>
                    <label for="cat_<?php echo $this->index; ?>">Category</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Draft is assigned to this WP category."></span>
                </th>
                <td>
                    <?php
                    wp_dropdown_categories([
                        'name'            => "aica_strategies[{$this->index}][category_id]",
                        'selected'        => intval($s['category_id']),
                        'show_option_none'=> '— Select —',
                        'hide_empty'      => 0,
                        'id'              => "cat_{$this->index}"
                    ]);
                    ?>
                </td>
            </tr>

            <!-- Images -->
            <tr>
                <th>
                    Auto-Generate Images
                    <span class="dashicons dashicons-editor-help"
                          title="When ticked, AI generates images with DALL·E 3 (extra cost)."></span>
                </th>
                <td>
                    <label>
                        <input type="checkbox"
                               name="aica_strategies[<?php echo $this->index; ?>][enable_images]"
                               value="1" <?php checked($s['enable_images'], 1); ?>> Enable
                    </label>
                    &nbsp; Images per post:
                    <input type="number" min="1" max="6" style="width:60px;"
                           name="aica_strategies[<?php echo $this->index; ?>][image_count]"
                           value="<?php echo esc_attr($s['image_count']); ?>">
                </td>
            </tr>

            <!-- Stop logic -->
            <?php $stop = $s['stop_condition']; ?>
            <tr>
                <th>
                    <label>When to stop</label>
                    <span class="dashicons dashicons-editor-help"
                          title="Automatic pause rules."></span>
                </th>
                <td>
                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][stop_condition]"
                               value="none" <?php checked($stop, 'none'); ?>> No end
                    </label><br>

                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][stop_condition]"
                               value="max_posts" <?php checked($stop, 'max_posts'); ?>> After
                    </label>
                    <input type="number" min="1" style="width:80px;"
                           name="aica_strategies[<?php echo $this->index; ?>][max_posts]"
                           value="<?php echo esc_attr($s['max_posts']); ?>"> posts<br>

                    <label>
                        <input type="radio"
                               name="aica_strategies[<?php echo $this->index; ?>][stop_condition]"
                               value="end_date" <?php checked($stop, 'end_date'); ?>> On date
                    </label>
                    <input type="date" style="margin-left:8px;"
                           name="aica_strategies[<?php echo $this->index; ?>][end_date]"
                           value="<?php echo esc_attr($s['end_date']); ?>">
                </td>
            </tr>
        </table>

        <!-- Buttons -->
        <p class="submit aica-strategy-actions" style="margin-top:20px;">
            <button type="submit" name="start_strategy" class="button button-primary">
                Start Strategy
            </button>
            <button type="submit" name="pause_strategy" class="button">
                Pause Strategy
            </button>
        </p>
        <?php
    }

    /*──────────────────────  Save  ──────────────────────*/
    public function handleSave(): void
    {
        $this->requireNonce('aica_save_' . $this->slug, '_wpnonce');

        $all   = get_option('aica_strategies', array_fill(0, 5, []));
        $in    = $_POST['aica_strategies'][$this->index] ?? [];
        $start = isset($_POST['start_strategy']);
        $pause = isset($_POST['pause_strategy']);
        $prev  = !empty($all[$this->index]['enabled']);

        /* Enabled flag comes only from buttons */
        $enabled = $pause ? false : ($start ? true : $prev);

        $min = max(300, intval($in['min_words'] ?? 600));

        $row = [
            'enabled'         => (int)$enabled,
            'source_url'      => esc_url_raw($in['source_url'] ?? ''),
            'kw_primary'      => sanitize_text_field($in['kw_primary'] ?? ''),
            'kw_secondary'    => sanitize_text_field($in['kw_secondary'] ?? ''),
            'tone'            => in_array($in['tone'] ?? 'conversational',
                                   ['professional','conversational','persuasive'], true)
                                ? $in['tone'] : 'conversational',
            'min_words'       => $min,
            'max_words'       => max($min + 100, intval($in['max_words'] ?? 1200)),
            'publish_time'    => preg_match('/^\d{2}:\d{2}$/', $in['publish_time'] ?? '')
                                ? $in['publish_time'] : '09:00',
            'recurrence'      => in_array($in['recurrence'] ?? 'daily',
                                   ['daily','interval','weekly','monthly'], true)
                                ? $in['recurrence'] : 'daily',
            'interval_days'   => max(0, intval($in['interval_days'] ?? 0)),
            'start_date'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['start_date'] ?? '')
                                ? $in['start_date'] : '',
            'weekdays'        => array_map('sanitize_text_field', $in['weekdays'] ?? []),
            'month_day'       => min(31, max(1, intval($in['month_day'] ?? 1))),
            'category_id'     => intval($in['category_id'] ?? get_option('default_category')),
            'stop_condition'  => in_array($in['stop_condition'] ?? 'none',
                                   ['none','max_posts','end_date'], true)
                                ? $in['stop_condition'] : 'none',
            'max_posts'       => max(0, intval($in['max_posts'] ?? 0)),
            'end_date'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['end_date'] ?? '')
                                ? $in['end_date'] : '',
            'enable_images'   => isset($in['enable_images']) ? 1 : 0,
            'image_count'     => min(6, max(1, intval($in['image_count'] ?? 3))),
        ];

        /* Validate when starting */
        if ($start) {
            $missing = [];
            if (!$row['source_url']) $missing[] = 'Source URL';
            if (!$row['kw_primary']) $missing[] = 'Primary Keyword';
            if ($missing) {
                set_transient('aica_error_' . get_current_user_id(),
                    'Missing: ' . implode(', ', $missing), 60);
                wp_safe_redirect(wp_get_referer());
                exit;
            }
        }

        /* Save row */
        $all[$this->index] = $row;
        update_option('aica_strategies', $all, false);

        /* Schedule / unschedule */
        if ($row['enabled']) {
            $next = (new \AICA\Cron\Scheduler())->calc_next_run(
                        new \AICA\Domain\ContentStrategy($row));
            wp_schedule_single_event($next, 'aica_strategy_event', [$this->index]);
        } else {
            wp_clear_scheduled_hook('aica_strategy_event', [$this->index]);
        }

        set_transient('aica_success_' . get_current_user_id(), 'Strategy saved', 60);
        wp_safe_redirect(wp_get_referer());
        exit;
    }
}
