<?php

namespace App\Http\Controllers;

use App\Content\PanelDay;
use Illuminate\View\View;

class AdminPreviewController extends Controller
{
    /**
     * The legacy illustrative studio panel day screen.
     *
     * It reads one illustrative day — `PanelDay` — and renders it. It is deliberately
     * **not** the working panel and must not grow into one:
     *
     *  - No actions. There is no create, move, cancel, contact or content editing on this
     *    screen, so there is nothing to authenticate or authorise yet. Real admin operations
     *    require authentication and server-side permission checks on every action (06, 07),
     *    and they will route through the same booking domain service as public checkout.
     *  - No models, no queries, no writes. Nothing here can touch a diary.
     *  - No invented people. Columns are service areas and visitors are lettered, so nothing
     *    on the screen can be mistaken for real customer or team data.
     *
     * The page states all of this in the shell banner and footer rather than relying on the
     * presenter to explain it.
     */
    public function __invoke(): View
    {
        // The preview only exists while it is switched on. With studio_panel.preview false
        // this is a plain 404.
        abort_unless((bool) config('gossips.studio_panel.preview'), 404);

        return view('admin.preview', [
            'columns' => PanelDay::columns(),
            'blocks' => PanelDay::blocks(),
            'statistics' => PanelDay::statistics(),
            'statuses' => PanelDay::statuses(),
            'dayLabel' => PanelDay::dayLabel(),
            'hourMarks' => PanelDay::hourMarks(),
            'gridHeight' => PanelDay::gridHeight(),
            'pixelsPerMinute' => PanelDay::PIXELS_PER_MINUTE,
        ]);
    }
}
