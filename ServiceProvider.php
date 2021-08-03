<?php

namespace Modules\System;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Modules\LivewireCore\Filesystem\PathResolver;

use Modules\System\Classes\PluginManager;
use Modules\System\Classes\SettingsManager;
use Modules\System\Classes\SideNavManager;
use Modules\Backend\Classes\WidgetManager;


use Arr;
use Str;
use BackendMenu;
use BackendAuth;
use Backend;
use Event;
use Livewire\Livewire;
use Modules\Backend\Models\UserRole;
use App;
use Illuminate\Foundation\AliasLoader;


class ServiceProvider extends BaseServiceProvider
{
    public $pluginsPath;
    public $tempPath;
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->extendArrStr();

        $this->registerProvider();
        $this->registerAlias();

        $this->app->instance('path.plugins', $this->pluginsPath());
        $this->app->instance('path.temp', $this->tempPath());

        $this->registerSingletons();

        PluginManager::instance()->registerAll();




        if ($this->app['execution.context']=='back-end') {
            $this->registerBackendNavigation();

            $this->registerBackendReportWidgets();
           // $this->registerBackendWidgets();
            $this->registerBackendPermissions();
           // $this->registerBackendSettings();
        }


    }

    protected function extendArrStr()
    {
        Arr::macro('build', function(array $array, callable $callback){
            $results = [];

            foreach ($array as $key => $value) {
                list($innerKey, $innerValue) = call_user_func($callback, $key, $value);

                $results[$innerKey] = $innerValue;
            }

            return $results;
        });
        Str::macro('normalizeClassName', function ($name){
            if (is_object($name)) {
                $name = get_class($name);
            }

            $name = '\\'.ltrim($name, '\\');
            return $name;
        });
        Str::macro('getClassId', function ($name){
            if (is_object($name)) {
                $name = get_class($name);
            }

            $name = ltrim($name, '\\');
            $name = str_replace('\\', '_', $name);

            return strtolower($name);
        });
        Str::macro('getClassNamespace', function ($name){
            $name = Str::normalizeClassName($name);
            return substr($name, 0, strrpos($name, "\\"));
        });
        Str::macro('getPrecedingSymbols', function($string, $symbol){
            return strlen($string) - strlen(ltrim($string, $symbol));
        });
    }

    protected function registerProvider()
    {
        $providers = include 'providers.php';

        foreach ($providers as $provider){
            App::register($provider);
        }
    }

    protected function registerAlias()
    {
        $aliases = include 'aliases.php';

        foreach ($aliases as $alias=>$class)
        {
            AliasLoader::getInstance()->alias($alias,$class);
        }
    }

        /**
     * Get the path to the public / web directory.
     *
     * @return string
     */
    public function tempPath()
    {
        return $this->tempPath ?: PathResolver::join(base_path(), '/storage/temp');
    }


    /*
     * Register navigation
     */
    protected function registerBackendNavigation()
    {
        BackendMenu::registerCallback(function ($manager) {
            $manager->registerMenuItems('Modules.System', [
                'system' => [
                    'label'       => 'system::lang.settings.menu_label',
                    'icon'        => 'icon-cog',
                    'iconSvg'     => 'modules/system/assets/images/cog-icon.svg',
                    'url'         => Backend::url('system/system'),
                    'permissions' => [],
                    'order'       => 1000
                ]
            ]);
            // $manager->registerOwnerAlias('Winter.System', 'October.System');
        });




        $this->booting(function () {
            /*
                    * Remove the Winter.System.system main menu item if there is no subpages to display
                    */
            // Event::listen('backend.menu.extendItems', function ($manager) {
            //     $systemSettingItems = SettingsManager::instance()->listItems('system');
            //     $systemMenuItems = $manager->listSideMenuItems('Modules.System', 'system');
            //     if (empty($systemSettingItems) && empty($systemMenuItems)) {
            //         $manager->removeMainMenuItem('Modules.System', 'system');
            //     }
            // }, -9999);
            Event::listen('backend.menu.extendItems', function ($manager) {
                // dd(32312);
                $systemSettingItems = SideNavManager::instance()->listItems('modules.system');
                $systemMenuItems = $manager->listSideMenuItems('Modules.System', 'system');

                if (empty($systemSettingItems) && empty($systemMenuItems)) {
                    $manager->removeMainMenuItem('Modules.System', 'system');
                }
            }, -9999);
        });
    }
        /*
     * Register report widgets
     */
    protected function registerBackendReportWidgets()
    {
        WidgetManager::instance()->registerReportWidgets(function ($manager) {
            $manager->registerReportWidget(\Modules\System\ReportWidgets\Status::class, [
                'label'   => 'backend::lang.dashboard.status.widget_title_default',
                'context' => 'dashboard1'
            ]);
        });
    }
    /**
     * Register singletons
     */
    protected function registerSingletons()
    {
        $this->app->singleton('backend.helper', function () {
            return new \Modules\Backend\Helpers\Backend;
        });

        $this->app->singleton('backend.menu', function () {
            return \Modules\Backend\Classes\NavigationManager::instance();
        });
        $this->app->singleton('backend.auth', function () {
            return \Modules\Backend\Classes\AuthManager::instance();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        // dd(Arr::class);

        PluginManager::instance()->bootAll();

    }

    public function pluginsPath()
    {
        return $this->pluginsPath ?: PathResolver::join(base_path(), '/Modules');
    }
    /**
    * Set the plugins path for the application.
    *
    * @param  string $path
    * @return $this
    */
    public function setPluginsPath($path)
    {
        $path = PathResolver::standardize($path);
        $this->pluginsPath = $path;
        $this->app->instance('path.plugins', $path);
        return $this;
    }



    /*
     * Register permissions
     */
    protected function registerBackendPermissions()
    {
        BackendAuth::registerCallback(function ($manager) {
            $manager->registerPermissions('Modules.System', [
                // 'system.manage_updates' => [
                //     'label' => 'system::lang.permissions.manage_software_updates',
                //     'tab' => 'system::lang.permissions.name',
                //     'roles' => UserRole::CODE_DEVELOPER,
                // ],
                'system.access_logs' => [
                    'label' => 'system::lang.permissions.access_logs',
                    'tab' => 'system::lang.permissions.name',
                    'roles' => UserRole::CODE_DEVELOPER,
                ],
                // 'system.manage_mail_settings' => [
                //     'label' => 'system::lang.permissions.manage_mail_settings',
                //     'tab' => 'system::lang.permissions.name',
                //     'roles' => UserRole::CODE_DEVELOPER,
                // ],
                // 'system.manage_mail_templates' => [
                //     'label' => 'system::lang.permissions.manage_mail_templates',
                //     'tab' => 'system::lang.permissions.name',
                //     'roles' => UserRole::CODE_DEVELOPER,
                // ]
            ]);
        });
    }
}
