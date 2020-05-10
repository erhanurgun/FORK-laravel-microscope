<?php

namespace Imanghafoori\LaravelMicroscope\Commands;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Routing\Router;
use Illuminate\Console\Command;
use Imanghafoori\LaravelMicroscope\Psr4Classes;
use Imanghafoori\LaravelMicroscope\CheckBladeFiles;
use Imanghafoori\LaravelMicroscope\Traits\LogsErrors;
use Imanghafoori\LaravelMicroscope\Checks\CheckRouteCalls;
use Imanghafoori\LaravelMicroscope\Checks\RoutelessActions;
use Imanghafoori\LaravelMicroscope\ErrorReporters\ErrorPrinter;

class CheckRoutes extends Command
{
    use LogsErrors;

    protected $signature = 'check:routes';

    protected $description = 'Checks the validity of route definitions';

    /**
     * Execute the console command.
     *
     * @param  ErrorPrinter  $errorPrinter
     *
     * @return mixed
     */
    public function handle(ErrorPrinter $errorPrinter)
    {
        $t1 = microtime(true);
        $this->info('Checking route definitions...');

        $errorPrinter->printer = $this->output;

//        $bar = $this->output->createProgressBar(count($routes));
//        $bar->start();
        $routes = app(Router::class)->getRoutes()->getRoutes();
        $this->checkRouteDefinitions($errorPrinter, $routes);
//        $bar->finish();

        // checks calls like this: route('admin.user')
        // in the psr-4 loaded classes.
        $this->info('Searching for route-less controller actions...');
        Psr4Classes::check([RoutelessActions::class]);

        $this->info('Checking route names exists...');
        Psr4Classes::check([CheckRouteCalls::class]);
        CheckBladeFiles::applyChecks([CheckRouteCalls::class]);

        $this->finishCommand($errorPrinter);
        $t4 = microtime(true);

        $this->info('Total elapsed time:'.(($t4 - $t1)).' seconds');
    }

    private function getRouteId($route)
    {
        if ($routeName = $route->getName()) {
            return 'Error on route name: '.$routeName;
        } else {
            return 'Error on route url: '.$route->uri();
        }
    }

    private function checkRouteDefinitions($errorPrinter, $routes)
    {
        foreach ($routes as $route) {
//            $bar->advance();

            if (! is_string($ctrl = $route->getAction()['uses'])) {
                continue;
            }

            [$ctrlClass, $method] = Str::parseCallback($ctrl, '__invoke');

            try {
                $ctrlObj = app()->make($ctrlClass);
            } catch (Exception $e) {
                $msg1 = $this->getRouteId($route);
                $msg2 = 'The controller can not be resolved: ';
                $errorPrinter->route($ctrlClass, $msg1, $msg2);

                continue;
            }

            if (! method_exists($ctrlObj, $method)) {
                $msg1 = $this->getRouteId($route);
                $msg2 = 'Absent Method: ';
                $errorPrinter->route($ctrl, $msg1, $msg2);
            }
        }
    }
}
