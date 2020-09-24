<?php
declare(strict_types = 1);

namespace App\Component\DebugBar\DataCollector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class SettingDataCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $cookieName;

    /**
     * @var string
     */
    private $defaultSetting;

    public function __construct(
        string $name = 'setting',
        string $cookieName = 'DebugBar.Setting',
        string $defaultSetting = '{"_": "default setting"}'
    ) {
        $this->name = $name;
        $this->cookieName = $cookieName . '.' . $this->name;
        $this->defaultSetting = $defaultSetting;
    }

    public function setDefaultSetting(string $defaultSetting)
    {
        $this->defaultSetting = $defaultSetting;
        return $this;
    }

    public function collect()
    {
        return [
            'cookie' => $this->cookieName,
            'default' => $this->defaultSetting,
        ];
    }

    public function getName()
    {
        return $this->name;
    }

    public function getWidgets()
    {
        return [
            'Setting' => [
                'tooltip' => $this->name,
                'widget'  => 'PhpDebugBar.Widgets.Setting',
                'map'     => $this->name,
                'default' => '""',
            ],
        ];
    }

    function getAssets()
    {
        return [
            'base_path' => __DIR__ . '/../Resources/widgets',
            'base_url'  => '/vendor/ngyuki/php-debugbar/widgets',
            'css'       => 'setting.css',
            'js'        => 'setting.js'
        ];
    }
}
