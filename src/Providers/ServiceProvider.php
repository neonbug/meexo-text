<?php namespace Neonbug\Text\Providers;

use App;
use Route;
use View;
use Config;
use \Illuminate\Routing\Router as Router;

class ServiceProvider extends \Neonbug\Common\Providers\BaseServiceProvider {
	
	const PACKAGE_NAME       = 'text';
	const PREFIX             = 'text';
	const ROLE               = 'text';
	const TABLE_NAME         = 'text';
	const CONTROLLER         = '\Neonbug\Text\Controllers\Controller';
	const ADMIN_CONTROLLER   = '\Neonbug\Text\Controllers\AdminController';
	const FULL_CONFIG_PREFIX = 'neonbug.text';
	
	/**
	 * Define your route model bindings, pattern filters, etc.
	 *
	 * @return  void
	 */
	public function boot()
	{
		//============
		//== ASSETS ==
		//============
		$this->loadViewsFrom(__DIR__.'/../resources/views', static::PACKAGE_NAME);
		$this->publishes([
			__DIR__.'/../resources/views' => base_path('resources/views/vendor/' . static::PACKAGE_NAME),
		]);
		
		$this->loadViewsFrom(__DIR__.'/../resources/admin_views', static::PACKAGE_NAME . '_admin');
		$this->publishesAdmin([
			__DIR__.'/../resources/admin_views' => base_path('resources/views/vendor/' . static::PACKAGE_NAME . '_admin'),
		]);
		
		$this->loadTranslationsFrom('/', static::PACKAGE_NAME);
		
		$this->publishes([
			__DIR__.'/../database/migrations/' => database_path('/migrations')
		], 'migrations');
		
		$this->publishes([
			__DIR__.'/../config/' . static::PACKAGE_NAME . '.php' => config_path('neonbug/' . static::PACKAGE_NAME . '.php'),
		]);
		
		//============
		//== ROUTES ==
		//============
		$language = App::make('Language');
		$languages = ($language == null ? [] : $language::all());
		$locale = ($language == null ? Config::get('app.default_locale') : $language->locale);
		
		$admin_language = App::make('AdminLanguage');
		$admin_locale = ($admin_language == null ? Config::get('app.admin_default_locale') : $admin_language->locale);
		
		$resource_repo = App::make('ResourceRepository');
		
		//frontend
		$slug_routes_at_root = Config::get(static::FULL_CONFIG_PREFIX . '.slug_routes_at_root', false);
		
		foreach ($languages as $language_item)
		{
			$slugs = 
				$language_item == null ?
					null : 
					$resource_repo->getSlugs($language_item->id_language, static::TABLE_NAME);
			
			Route::group([ 'middleware' => [ 'online' ], 'prefix' => $language_item->locale ], 
				function($router) use ($slugs, $slug_routes_at_root, $language_item, $locale)
			{
				$router->group([ 'prefix' => trans(static::PACKAGE_NAME . '::frontend.route.prefix', [], 'messages', $language_item->locale) ], 
					function($router) use ($slugs, $slug_routes_at_root, $language_item, $locale)
				{
					$lang_postfix = '::' . $language_item->locale;
					$route_prefix = static::PACKAGE_NAME . '::frontend.route.';
					
					/*
					 * If language_item is current language, then we need to
					 * create all routes twice - once with lang_postfix and once without.
					 * Order matters - without language postfix should be at the end
					 * to enable Route::currentRouteName to return this route, instead of
					 * one of the routes with language postfix.
					 */
					$postfixes = 
						$language_item->locale == $locale ? 
							[ $lang_postfix, '' ] : 
							[ $lang_postfix ];
					
					foreach ($postfixes as $postfix)
					{
						$router->get('/', [
							'as'   => static::PREFIX . '::index' . $postfix, 
							'uses' => static::CONTROLLER . '@index'
						]);
						$router->get('index', [
							'as'   => static::PREFIX . '::index-with-name' . $postfix, 
							'uses' => static::CONTROLLER . '@index'
						]);
						$router->get('item/{id}', [
							'as'   => static::PREFIX . '::item' . $postfix, 
							'uses' => static::CONTROLLER . '@item'
						]);
						$router->get('preview/{key}', [
							'as'   => static::PREFIX . '::preview' . $postfix, 
							'uses' => static::CONTROLLER . '@preview'
						]);
					}
					
					if ($slugs != null)
					{
						$this->setRoutesFromSlugs(
							$router, 
							$slugs, 
							($slug_routes_at_root === true ? 'default' : ''), 
							$language_item->locale
						);
						
						/*
						 * Order matters - route without language postfix should be at the end
						 * to enable Route::currentRouteName to return this route, instead of
						 * one of the routes with language postfix
						 */
						if ($language_item->locale == $locale) // current language
						{
							$this->setRoutesFromSlugs(
								$router, 
								$slugs, 
								($slug_routes_at_root === true ? 'default' : '')
							);
						}
					}
				});

				//put routes at root level (i.e. /en/contents/abc is also accessible via /en/abc)
				if ($slug_routes_at_root)
				{
					if ($slugs != null)
					{
						$this->setRoutesFromSlugs(
							$router, 
							$slugs, 
							'', 
							$language_item->locale
						);
						
						/*
						 * Order matters - route without language postfix should be at the end
						 * to enable Route::currentRouteName to return this route, instead of
						 * one of the routes with language postfix
						 */
						if ($language_item->locale == $locale) // current language
						{
							$this->setRoutesFromSlugs(
								$router, 
								$slugs, 
								''
							);
						}
					}
				}
			});
		}
		
		//admin
		Route::group([ 'prefix' => $admin_locale . '/admin/' . static::PREFIX, 
			'middleware' => [ 'auth.admin', 'admin.menu' ], 'role' => static::ROLE, 
			'menu.icon' => 'file text outline' ], function($router)
		{
			$router->get('list', [
				'as'   => static::PREFIX . '::admin::list', 
				'uses' => static::ADMIN_CONTROLLER . '@adminList'
			]);
			
			$router->get('add', [
				'as'   => static::PREFIX . '::admin::add', 
				'uses' => static::ADMIN_CONTROLLER . '@adminAdd'
			]);
			$router->post('add', [
				'as'   => static::PREFIX . '::admin::add-save', 
				'uses' => static::ADMIN_CONTROLLER . '@adminAddPost'
			]);
			
			$router->get('edit/{id}', [
				'as'   => static::PREFIX . '::admin::edit', 
				'uses' => static::ADMIN_CONTROLLER . '@adminEdit'
			]);
			$router->post('edit/{id}', [
				'as'   => static::PREFIX . '::admin::edit-save', 
				'uses' => static::ADMIN_CONTROLLER . '@adminEditPost'
			]);
			
			$router->post('delete', [
				'as'   => static::PREFIX . '::admin::delete', 
				'uses' => static::ADMIN_CONTROLLER . '@adminDeletePost'
			]);
			
			$router->post('check-slug', [
				'as'   => static::PREFIX . '::admin::check-slug', 
				'uses' => static::ADMIN_CONTROLLER . '@adminCheckSlugPost'
			]);
		});

		parent::boot();
	}
	
	protected function setRoutesFromSlugs($router, $slugs, $route_name_prefix_postfix = '', $route_name_postfix = '')
	{
		$prefix_postfix = ($route_name_prefix_postfix == '' ? '' : '-' . $route_name_prefix_postfix);
		$route_name_prefix = static::PREFIX . '::slug' . $prefix_postfix . '::';
		
		$postfix = ($route_name_postfix == '' ? '' : '::' . $route_name_postfix);
		
		foreach ($slugs as $slug)
		{
			// skip empty slugs
			if ($slug->value == '') continue;
			
			/*
			 * Order matters - route without language postfix should be at the end
			 * to enable Route::currentRouteName to return this route, instead of
			 * one of the routes with language postfix
			 */
			foreach ([
				$route_name_prefix . $slug->value . $postfix, 
				$route_name_prefix . 'item-' . $slug->id_row . $postfix, 
			] as $route_alias) {
				$router->get($slug->value, [ 'as' => $route_alias, 
					function() use ($slug) {
					$controller = App::make(static::CONTROLLER);
					return $controller->callAction('item', [ 'id' => $slug->id_row ]);
				} ]);
			}
		}
	}

	/**
	 * Register any application services.
	 *
	 * This service provider is a great spot to register your various container
	 * bindings with the application. As you can see, we are registering our
	 * "Registrar" implementation here. You can add your own bindings too!
	 *
	 * @return  void
	 */
	public function register()
	{
		//===========
		//== BINDS ==
		//===========
		if (!App::bound('\Neonbug\Text\Repositories\TextRepository'))
		{
			App::singleton('\Neonbug\Text\Repositories\TextRepository', '\Neonbug\Text\Repositories\TextRepository');
		}
	}

}
