<?php

namespace SilverStripe\Forms\Tests\HTMLEditor;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\HTMLEditor\HTMLEditorConfig;
use SilverStripe\Forms\HTMLEditor\TinyMCECombinedGenerator;
use SilverStripe\Forms\HTMLEditor\TinyMCEConfig;
use SilverStripe\View\SSViewer;

class TinyMCECombinedGeneratorTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();

        // Set custom base_path for tinymce
        Director::config()->set('alternate_base_folder', __DIR__ . '/TinyMCECombinedGeneratorTest');
        Director::config()->set('alternate_base_url', 'http://www.mysite.com/basedir/');
        SSViewer::config()->set('themes', [SSViewer::DEFAULT_THEME]);
        TinyMCEConfig::config()->set('base_dir', 'tinymce');
    }

    protected function tearDown()
    {
        parent::tearDown();
        // Flush test configs
        HTMLEditorConfig::set_config('testconfig', null);
    }

    public function testConfig()
    {
        // Disable nonces
        $c = new TinyMCEConfig();
        $c->setTheme('testtheme');
        $c->setOption('language', 'en');
        $c->disablePlugins('table', 'emoticons', 'paste', 'code', 'link', 'importcss');
        $c->enablePlugins(
            array(
                'plugin1' => 'mycode/plugin1.js', //
                'plugin2' => '/anotherbase/mycode/plugin2.js',
                'plugin3' => 'https://www.google.com/mycode/plugin3.js',
                'plugin4' => null,
                'plugin5' => null,
                'plugin6' => '/basedir/mycode/plugin6.js',
                'plugin7' => '/basedir/mycode/plugin7.js',
            )
        );
        HTMLEditorConfig::set_config('testconfig', $c);

        // Get config for this
        /** @var TinyMCECombinedGenerator $generator */
        $generator = Injector::inst()->create(TinyMCECombinedGenerator::class);
        $this->assertEquals(
            '_tinymce/tinymce-testconfig-6422b3814d.js',
            $generator->generateFilename($c),
            "Filename for config: " . json_encode($c->getAttributes()) . " should match expected value"
        );
        $content = $generator->generateContent($c);
        $this->assertContains(
            "var baseURL = baseTag.length ? baseTag[0].baseURI : 'http://www.mysite.com/basedir/';\n",
            $content
        );
        // Main script file
        $this->assertContains("/* tinymce.js */\n", $content);
        // Locale file
        $this->assertContains("/* en.js */\n", $content);
        // Local plugins
        $this->assertContains("/* plugin1.js */\n", $content);
        $this->assertContains("/* plugin4.min.js */\n", $content);
        $this->assertContains("/* plugin4/langs/en.js */\n", $content);
        $this->assertContains("/* plugin5.js */\n", $content);
        $this->assertContains("/* plugin6.js */\n", $content);
        // Exclude non-local plugins
        $this->assertNotContains('plugin2.js', $content);
        $this->assertNotContains('plugin3.js', $content);
        // Exclude missing file
        $this->assertNotContains('plugin7.js', $content);

        // Check themes
        $this->assertContains("/* theme.js */\n", $content);
        $this->assertContains("/* testtheme/langs/en.js */\n", $content);

        // Register done scripts
        $this->assertContains(
            <<<EOS
tinymce.each('tinymce/langs/en.js,mycode/plugin1.js,tinymce/plugins/plugin4/plugin.min.js,tinymce/plugins/plugin4/langs/en.js,tinymce/plugins/plugin5/plugin.js,mycode/plugin6.js,tinymce/themes/testtheme/theme.js,tinymce/themes/testtheme/langs/en.js'.split(','),function(f){tinymce.ScriptLoader.markDone(baseURL+f);});
EOS
            ,
            $content
        );
    }

    public function testFlush()
    {
        // Disable nonces
        $c = new TinyMCEConfig();
        $c->setTheme('testtheme');
        $c->setOption('language', 'en');
        $c->disablePlugins('table', 'emoticons', 'paste', 'code', 'link', 'importcss');
        $c->enablePlugins(['plugin1' => 'mycode/plugin1.js']);
        HTMLEditorConfig::set_config('testconfig', $c);

        // Generate file for this
        /** @var TinyMCECombinedGenerator $generator */
        $generator = Injector::inst()->create(TinyMCECombinedGenerator::class);
        $generator->getScriptURL($c);
        $filename = $generator->generateFilename($c);

        // Ensure content exists
        $this->assertNotEmpty($generator->getAssetHandler()->getContent($filename));

        // Flush should destroy this
        TinyMCECombinedGenerator::flush();
        $this->assertEmpty($generator->getAssetHandler()->getContent($filename));
    }
}