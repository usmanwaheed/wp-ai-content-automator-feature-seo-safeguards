<?php
namespace AICA\Admin\Tabs;

/**
 * Base class for admin settings tabs.
 */
abstract class BaseTab
{
    protected string $slug;
    protected string $label;

    public function __construct(string $slug, string $label)
    {
        $this->slug  = $slug;
        $this->label = $label;

        add_action('aica_render_tab_' . $slug, [$this, 'render']);
        add_action('admin_post_aica_save_' . $slug, [$this, 'handleSave']);
    }

    /** Public accessors */
    public function getSlug(): string  { return $this->slug; }
    public function getLabel(): string { return $this->label; }

    /** Must output settings-HTML. */
    abstract public function render(): void;

    /** Must process form submission.  *PUBLIC* so WP can invoke it via admin-post. */
    abstract public function handleSave(): void;

    /*──────────  Helper  ──────────*/
    protected function requireNonce(string $action, string $field): void
    {
        if (
            !current_user_can('manage_options') ||
            !check_admin_referer($action, $field)
        ) {
            wp_die('Unauthorised', 403);
        }
    }
}
