<?php

namespace App\Http\Controllers\Api\V1\Study;

use App\Http\Controllers\Controller;
use App\Http\Requests\Study\StudyReminderRequest;
use App\Http\Resources\Study\StudyReminderResource;
use App\Models\StudyReminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * PASINDU — recurring study-session reminders.
 */
class StudyReminderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return StudyReminderResource::collection($request->user()->studyReminders()->with('module')->orderBy('remind_time')->get());
    }

    public function store(StudyReminderRequest $request): JsonResponse
    {
        $reminder = $request->user()->studyReminders()->create($request->validated());
        activity()->action('study.reminder_created')
            ->describe("Created study reminder “{$reminder->title}” at {$request->validated('remind_time')} on ".implode(', ', $reminder->days))->on($reminder);

        return StudyReminderResource::make($reminder->load('module'))->response()->setStatusCode(201);
    }

    public function update(StudyReminderRequest $request, StudyReminder $reminder): StudyReminderResource
    {
        $reminder->update($request->validated());
        activity()->action('study.reminder_updated')->describe("Updated study reminder “{$reminder->title}”")->on($reminder);

        return StudyReminderResource::make($reminder->load('module'));
    }

    public function destroy(StudyReminder $reminder): JsonResponse
    {
        $reminder->delete();
        activity()->action('study.reminder_deleted')->describe("Deleted study reminder “{$reminder->title}”");

        return response()->json(['message' => 'Reminder deleted.']);
    }
}
