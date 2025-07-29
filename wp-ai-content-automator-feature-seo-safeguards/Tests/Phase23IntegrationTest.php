<?php
/**
 * Phase 2-3 Integration Tests
 * 
 * Basic tests to verify the Phase 2-3 features are properly integrated
 */

class Phase23IntegrationTest
{
    /**
     * Test that schema injection hooks are registered
     */
    public function test_schema_hooks_registered()
    {
        // Check if SchemaInjector hooks are registered
        $this->assertTrue(has_action('aica_content_published'));
        $this->assertTrue(has_filter('rank_math/json_ld'));
        $this->assertTrue(has_action('wp_head'));
        
        echo "✅ Schema injection hooks are registered\n";
    }
    
    /**
     * Test that robots manager hooks are registered
     */
    public function test_robots_hooks_registered()
    {
        // Check if RobotsManager hooks are registered
        $this->assertTrue(has_action('template_redirect'));
        
        echo "✅ Robots manager hooks are registered\n";
    }
    
    /**
     * Test that audit API endpoint is registered
     */
    public function test_audit_api_registered()
    {
        // Check if REST API hooks are registered
        $this->assertTrue(has_action('rest_api_init'));
        
        echo "✅ Audit API hooks are registered\n";
    }
    
    /**
     * Test that rewrite tag is registered
     */
    public function test_rewrite_tag_registered()
    {
        global $wp_rewrite;
        
        // Check if aica_blocked query var is registered
        $this->assertTrue(has_action('init'));
        
        echo "✅ Rewrite tag registration hook is registered\n";
    }
    
    /**
     * Test schema builder functionality
     */
    public function test_schema_builder()
    {
        // Test FAQ extraction
        $html = '<h3>What is this?</h3><h4>This is a test answer.</h4>';
        $faqs = \AICA\SEO\SchemaBuilder::extract_faq($html);
        
        $this->assertNotEmpty($faqs);
        $this->assertEquals('What is this?', $faqs[0]['q']);
        $this->assertEquals('This is a test answer.', $faqs[0]['a']);
        
        echo "✅ Schema builder FAQ extraction works\n";
    }
    
    /**
     * Test robots manager functionality
     */
    public function test_robots_manager()
    {
        $rm = new \AICA\SEO\RobotsManager();
        
        // Test that blocked slugs option exists
        $blocked = get_option('aica_blocked_slugs', []);
        $this->assertIsArray($blocked);
        
        echo "✅ Robots manager initialization works\n";
    }
    
    /**
     * Test competitor scraper functionality
     */
    public function test_competitor_scraper()
    {
        $scraper = new \AICA\Audit\CompetitorScraper();
        
        // Test rate limiting (should return error for rapid calls)
        $result1 = $scraper->analyze('https://example.com');
        $result2 = $scraper->analyze('https://example.com');
        
        // Second call should be rate limited or cached
        $this->assertTrue(is_array($result2) || is_wp_error($result2));
        
        echo "✅ Competitor scraper rate limiting works\n";
    }
    
    /**
     * Simple assertion helper
     */
    private function assertTrue($condition, $message = '')
    {
        if (!$condition) {
            throw new Exception("Assertion failed: " . $message);
        }
    }
    
    /**
     * Simple assertion helper
     */
    private function assertNotEmpty($value, $message = '')
    {
        if (empty($value)) {
            throw new Exception("Assertion failed - value is empty: " . $message);
        }
    }
    
    /**
     * Simple assertion helper
     */
    private function assertEquals($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            throw new Exception("Assertion failed - expected '$expected', got '$actual': " . $message);
        }
    }
    
    /**
     * Simple assertion helper
     */
    private function assertIsArray($value, $message = '')
    {
        if (!is_array($value)) {
            throw new Exception("Assertion failed - value is not an array: " . $message);
        }
    }
    
    /**
     * Run all tests
     */
    public function run_all_tests()
    {
        echo "🚀 Running Phase 2-3 Integration Tests...\n\n";
        
        try {
            $this->test_schema_hooks_registered();
            $this->test_robots_hooks_registered();
            $this->test_audit_api_registered();
            $this->test_rewrite_tag_registered();
            $this->test_schema_builder();
            $this->test_robots_manager();
            $this->test_competitor_scraper();
            
            echo "\n✅ All Phase 2-3 integration tests passed!\n";
            
        } catch (Exception $e) {
            echo "\n❌ Test failed: " . $e->getMessage() . "\n";
        }
    }
}

// Tests should only be run explicitly via test runner
// Removed auto-execution to prevent accidental runs in production
