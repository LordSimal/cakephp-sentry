<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Event\EventListenerInterface;

class EventListener implements EventListenerInterface
{
    use EventSpanTrait;

    /**
     * Return an array of events to listen to.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        $before = function ($name) {
            return function () use ($name): void {
                DebugTimer::start($name);
            };
        };
        $after = function ($name) {
            return function () use ($name): void {
                DebugTimer::stop($name);
            };
        };
        $both = function ($name) use ($before, $after) {
            return [
                ['priority' => 0, 'callable' => $before('Event: ' . $name)],
                ['priority' => 999, 'callable' => $after('Event: ' . $name)],
            ];
        };

        return [
            'Controller.initialize' => [
                ['priority' => 0, 'callable' => $before('Event: Controller.initialize')],
                ['priority' => 999, 'callable' => $after('Event: Controller.initialize')],
            ],
            'Controller.startup' => [
                ['priority' => 0, 'callable' => $before('Event: Controller.startup')],
                ['priority' => 999, 'callable' => $after('Event: Controller.startup')],
                ['priority' => 999, 'callable' => function (): void {
                    DebugTimer::start('Controller: action');
                }],
            ],
            'Controller.beforeRender' => [
                ['priority' => 0, 'callable' => function (): void {
                    DebugTimer::stop('Controller: action');
                }],
                ['priority' => 0, 'callable' => $before('Event: Controller.beforeRender')],
                ['priority' => 999, 'callable' => $after('Event: Controller.beforeRender')],
                ['priority' => 999, 'callable' => function (): void {
                    DebugTimer::start('View: Render');
                }],
            ],
            'View.beforeRender' => $both('View.beforeRender'),
            'View.afterRender' => $both('View.afterRender'),
            'View.beforeLayout' => $both('View.beforeLayout'),
            'View.afterLayout' => $both('View.afterLayout'),
            'Cell.beforeAction' => [
                ['priority' => 0, 'callable' => function ($event, $cell, $action): void {
                    DebugTimer::start('Cell.Action ' . get_class($cell) . '::' . $action);
                }],
            ],
            'Cell.afterAction' => [
                ['priority' => 0, 'callable' => function ($event, $cell, $action): void {
                    DebugTimer::stop('Cell.Action ' . get_class($cell) . '::' . $action);
                }],
            ],
            'View.beforeRenderFile' => [
                ['priority' => 0, 'callable' => function ($event, $filename): void {
                    DebugTimer::start('Render File: ' . $filename);
                }],
            ],
            'View.afterRenderFile' => [
                ['priority' => 0, 'callable' => function ($event, $filename): void {
                    DebugTimer::stop('Render File: ' . $filename);
                }],
            ],
            'Controller.shutdown' => [
                ['priority' => 0, 'callable' => $before('Event: Controller.shutdown')],
                ['priority' => 0, 'callable' => function (): void {
                    DebugTimer::stop('View: Render');
                }],
                ['priority' => 999, 'callable' => $after('Event: Controller.shutdown')],
            ],
        ];
    }

    /**
     * @return void
     */
    public function addSpans(): void
    {
        foreach (DebugTimer::getAll() as $message => $event) {
            $op = match (true) {
                str_starts_with($message, 'View:') => 'view.render',
                str_starts_with($message, 'Event: View.') => 'view.render',
                str_starts_with($message, 'Render File:') => 'view.render',
                true => 'default'
            };
            $this->addEventSpan($message, $op, $event['start'], $event['end']);
        }
    }
}
