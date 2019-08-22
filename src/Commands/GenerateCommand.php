<?php

namespace Webflorist\StaticRoutes\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Webflorist\StaticRoutes\Crawlers\HttpRequestCrawler;
use Webflorist\StaticRoutes\Exceptions\RouteGenerationException;

class GenerateCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'static-routes:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates static routes.';

    /**
     * Create a new command instance.
     *
     * @param HttpRequestCrawler $crawler
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param Router $router
     * @return mixed
     * @throws RouteGenerationException
     */
    public function handle(Router $router)
    {
        // Set environment to production.
        config()->set('app.env', 'production');
        app()['env'] = 'production';

        // Set debug to false
        config()->set('app.debug', false);

        $crawler = new HttpRequestCrawler(app());

        // Set a pseudo-config key to let application know,
        // a static generation is happening.
        config()->set('static-routes.is_generating', true);

        $outputBasePath = config('static-routes.output_path');

        self::rrmdir($outputBasePath);

        foreach ($router->getRoutes()->getRoutesByMethod()['GET'] as $route) {

            $uri = $route->uri();

            if ($this->uriIsExcluded($uri)) {
                $this->line("$uri: excluded via config.");
                continue;
            }

            /** @var Route $route */
            $outputFile = "$outputBasePath/$uri/index.html";
            $outputPath = substr($outputFile, 0, strrpos($outputFile, '/'));

            if (!file_exists($outputPath)) {
                mkdir($outputPath, 0777, true);
            }

            $response = $crawler->get(
                $uri
            );

            if (!is_null($response->exception)) {
                $exceptionInfo = get_class($response->exception).':'.$response->exception->getMessage();
                throw new RouteGenerationException("Route with URI '$uri' threw Exception:'$exceptionInfo'");
            }

            if ($response->isRedirection()) {
	        // TODO: Handle redirections.
                $redirectTarget = $response->baseResponse->headers->get('Location');
                $this->line("$uri: excluded due to redirection.");
                continue;
            }

            file_put_contents($outputFile, $response->content());

            $this->info("$uri: generated successfully.");

        }

        $this->info("Static routes generation successfully completed.");
    }

    /**
     * Recursively remove a directory.
     *
     * @param string $dir
     */
    static function rrmdir(string $dir) : void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        self::rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Check if $uri is excluded via config.
     *
     * @param string $uri
     * @return bool
     */
    private function uriIsExcluded(string $uri)
    {
        foreach (config('static-routes.excluded_paths') as $excludedPath) {
            if ($uri === $excludedPath) {
                return true;
            }
            if (strpos($uri,$excludedPath.'/') === 0) {
                return true;
            }
        }
        return false;
    }
}
