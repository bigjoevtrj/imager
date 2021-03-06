<?php namespace TippingCanoe\Imager;

use Illuminate\Support\ServiceProvider as Base;
use Illuminate\Foundation\Application;
use Intervention\Image\ImageManager;


class ServiceProvider extends Base {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot() {
		$this->package('tippingcanoe/imager');
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {

		/** @var \Illuminate\Config\Repository $config */
		$config = $this->app->make('config');

		$this->app->singleton('Intervention\Image\ImageManager', function (Application $app) {
			return new ImageManager(array('driver'=>$app->config->get('imager::driver', 'gd')));
		});

		$this->app->bind('TippingCanoe\Imager\Repository\Image', 'TippingCanoe\Imager\Repository\DbImage');
		

		$this->app->singleton('TippingCanoe\Imager\Service', function (Application $app) use ($config) {

			//
			// Amazon S3
			//
			if($s3Config = $config->get('imager::s3')) {
				$this->app->bind('Aws\S3\S3Client', function (Application $app) use ($s3Config) {
					return \Aws\S3\S3Client::factory($s3Config);
				});
			}

			// Run through and call each config option as a setter on the storage method.
			$storageDrivers = [];
			foreach($config->get('imager::storage', []) as $abstract => $driverConfig) {

				/** @var \TippingCanoe\Imager\Storage\Driver $driver */
				$driver = $app->make($abstract);

				foreach($driverConfig as $property => $value) {
					$setter = studly_case('set_' . $property);
					$driver->$setter($value);
				}

				$storageDrivers[$abstract] = $driver;

			}

			$service = new Service(
				$app->make('TippingCanoe\Imager\Repository\Image'),
				$app->make('Intervention\Image\ImageManager'),
				$app,
				$storageDrivers
			);

			return $service;

		});


	}
        
    /**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides() {
		return [
			'Intervention\Image\ImageManager',
			'TippingCanoe\Imager\Service',
			'TippingCanoe\Imager\Repository\Image'
		];
	}

}
