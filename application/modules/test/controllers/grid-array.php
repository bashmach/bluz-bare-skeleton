<?php
/**
 * Example of grid
 *
 * @author   Anton Shevchuk
 * @created  27.08.12 10:08
 */
namespace Application;

use Application\Test;
use Bluz\Proxy\Layout;

return
/**
 * @return \closure
 */
function () use ($view, $module, $controller) {
    /**
     * @var Bootstrap $this
     * @var \Bluz\View\View $view
     */
    Layout::breadCrumbs(
        [
            $view->ahref('Test', ['test', 'index']),
            'Grid with Array',
        ]
    );
    $grid = new Test\ArrayGrid();
    $grid->setModule($module);
    $grid->setController($controller);

    $view->grid = $grid;
};
