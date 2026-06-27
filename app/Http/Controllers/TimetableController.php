<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use App\Models\SectionAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TimetableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Timetable::query()->with(['sectionAssignments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $timetables = $query->get()->map(function ($item) {
            return [
                'id'                    => $item->id,
                'section_assignments_id' => $item->section_assignments_id,
                'section_name'          => $item->sectionAssignments->section->name,
                'section_id'          => $item->sectionAssignments->section->id,
                'subject_name'          => $item->sectionAssignments->subject->name,
                'subject_id'          => $item->sectionAssignments->subject->id,
                'lecturer_name'         => $item->sectionAssignments->lecturer->user->name,
                'lecturer_id'         => $item->sectionAssignments->lecturer->user->id,
                'day_of_week'           => $item->day_of_week,
                'start_time'            => $item->start_time,
                'end_time'              => $item->end_time,
                'room_number'           => $item->room_number,
                'link'                  => $item->link,
                'status'                => $item->status,
                'created_at'            => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Timetables retrieved successfully',
            'data'    => [
                'timetables' => $timetables
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'section_assignments_id' => 'required|exists:section_assignments,id',
            'day_of_week'           => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time'            => [
                'required',
                'date_format:H:i',
                Rule::unique('timetables')->where(function ($query) use ($request) {
                    return $query->where('section_assignments_id', $request->section_assignments_id)
                                ->where('day_of_week', $request->day_of_week)
                                ->where('start_time', $request->start_time);
                }),
            ],
            'end_time'              => 'required|date_format:H:i|after:start_time',
            'room_number'           => 'nullable|string|max:50',
            'link'        => 'nullable|url|max:255',
        ], [
            'start_time.unique' => 'This timetable slot already exists for this assignment.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $currentAssignment = SectionAssignment::find($request->section_assignments_id);
        if ($currentAssignment) {
            $lecturerBusy = Timetable::where('status', 'active')
                ->where('day_of_week', $request->day_of_week)
                ->whereHas('sectionAssignments', function ($query) use ($currentAssignment) {
                    $query->where('lecturer_id', $currentAssignment->lecturer_id);
                })
                ->where(function ($query) use ($request) {
                    $query->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })->exists();

            if ($lecturerBusy) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The lecturer is already assigned to another active class during this time period.',
                ], 422);
            }
        }

        if ($request->room_number) {
            $exists = Timetable::where('status', 'active')
                ->where('day_of_week', $request->day_of_week)
                ->where('room_number', $request->room_number)
                ->where(function ($query) use ($request) {
                    $query->where('start_time', '<', $request->end_time)
                        ->where('end_time', '>', $request->start_time);
                })->exists();

            if ($exists) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The room is already occupied during this time period.',
                ], 422);
            }
        }

        $timetable = Timetable::create([
            'section_assignments_id' => $request->section_assignments_id,
            'day_of_week'           => $request->day_of_week,
            'start_time'            => $request->start_time,
            'end_time'              => $request->end_time,
            'room_number'           => $request->room_number,
            'link'                  => $request->link,
            'status'                => 'active',
        ]);

        $timetable->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Timetable entry created successfully',
            'data'    => [
                'timetable' => [
                    'id'                    => $timetable->id,
                    'section_assignments_id' => $timetable->section_assignments_id,
                    'day_of_week'           => $timetable->day_of_week,
                    'start_time'            => $timetable->start_time,
                    'end_time'              => $timetable->end_time,
                    'room_number'           => $timetable->room_number,
                    'link'                  => $timetable->link,
                    'status'                => $timetable->status,
                    'created_at'            => $timetable->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $timetable = Timetable::with(['sectionAssignments'])->find($id);

        if (!$timetable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Timetable entry not found'
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Timetable details retrieved',
            'data'    => [
                'timetable' => [
                    'id'                    => $timetable->id,
                    'section_assignments_id' => $timetable->section_assignments_id,
                    'day_of_week'           => $timetable->day_of_week,
                    'start_time'            => $timetable->start_time,
                    'end_time'              => $timetable->end_time,
                    'room_number'           => $timetable->room_number,
                    'link'                  => $timetable->link,
                    'status'                => $timetable->status,
                    'created_at'            => $timetable->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $timetable = Timetable::find($id);

        if (!$timetable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Timetable entry not found'
            ], 404);
        }

        $sectionAssignmentId = $request->input('section_assignments_id', $timetable->section_assignments_id);
        $dayOfWeek           = $request->input('day_of_week', $timetable->day_of_week);
        $startTime           = $request->input('start_time', $timetable->start_time);
        $endTime             = $request->input('end_time', $timetable->end_time);
        $roomNumber          = $request->input('room_number', $timetable->room_number);

        $validator = Validator::make($request->all(), [
            'section_assignments_id' => 'exists:section_assignments,id',
            'day_of_week'           => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time'            => [
                'required',
                'date_format:H:i',
                Rule::unique('timetables')->ignore($timetable->id)->where(function ($query) use ($sectionAssignmentId, $dayOfWeek, $startTime) {
                    return $query->where('section_assignments_id', $sectionAssignmentId)
                                ->where('day_of_week', $dayOfWeek)
                                ->where('start_time', $startTime);
                }),
            ],
            'end_time'              => 'required|date_format:H:i|after:start_time',
            'room_number'           => 'nullable|string|max:50',
            'link'        => 'nullable|url|max:255',
        ], [
            'start_time.unique' => 'This timetable slot already exists for this assignment.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $currentAssignment = SectionAssignment::find($sectionAssignmentId);
        if ($currentAssignment) {
            $lecturerBusy = Timetable::where('id', '!=', $timetable->id)
                ->where('status', 'active')
                ->where('day_of_week', $dayOfWeek)
                ->whereHas('sectionAssignments', function ($query) use ($currentAssignment) {
                    $query->where('lecturer_id', $currentAssignment->lecturer_id);
                })
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                })->exists();

            if ($lecturerBusy) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The lecturer is already assigned to another active class during this time period.',
                ], 422);
            }
        }

        if ($roomNumber) {
            $exists = Timetable::where('id', '!=', $timetable->id)
                ->where('status', 'active')
                ->where('day_of_week', $dayOfWeek)
                ->where('room_number', $roomNumber)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                })->exists();

            if ($exists) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'The room is already occupied during this time period.',
                ], 422);
            }
        }

        $timetable->update([
            'section_assignments_id' => $sectionAssignmentId,
            'day_of_week'           => $dayOfWeek,
            'start_time'            => $startTime,
            'end_time'              => $endTime,
            'room_number'           => $roomNumber,
            'link'                  => $request->input('link', $timetable->link),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Timetable updated successfully',
            'data'    => [
                'timetable' => [
                    'id'                    => $timetable->id,
                    'section_assignments_id' => $timetable->section_assignments_id,
                    'day_of_week'           => $timetable->day_of_week,
                    'start_time'            => $timetable->start_time,
                    'end_time'              => $timetable->end_time,
                    'room_number'           => $timetable->room_number,
                    'link'                  => $timetable->link,
                    'status'                => $timetable->status,
                    'created_at'            => $timetable->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus($id): JsonResponse
    {
        $timetable = Timetable::find($id);

        if (!$timetable) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Timetable entry not found'
            ], 404);
        }

        $targetStatus = ($timetable->status === 'active') ? 'inactive' : 'active';

        if ($targetStatus === 'active') {
            
            $currentAssignment = SectionAssignment::find($timetable->section_assignments_id);
            if ($currentAssignment) {
                $lecturerBusy = Timetable::where('id', '!=', $timetable->id)
                    ->where('status', 'active')
                    ->where('day_of_week', $timetable->day_of_week)
                    ->whereHas('sectionAssignments', function ($query) use ($currentAssignment) {
                        $query->where('lecturer_id', $currentAssignment->lecturer_id);
                    })
                    ->where(function ($query) use ($timetable) {
                        $query->where('start_time', '<', $timetable->end_time)
                            ->where('end_time', '>', $timetable->start_time);
                    })->exists();

                if ($lecturerBusy) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Cannot activate: The lecturer is already assigned to another active class during this time period.',
                    ], 422);
                }
            }

            if ($timetable->room_number) {
                $roomOccupied = Timetable::where('id', '!=', $timetable->id)
                    ->where('status', 'active')
                    ->where('day_of_week', $timetable->day_of_week)
                    ->where('room_number', $timetable->room_number)
                    ->where(function ($query) use ($timetable) {
                        $query->where('start_time', '<', $timetable->end_time)
                            ->where('end_time', '>', $timetable->start_time);
                    })->exists();

                if ($roomOccupied) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Cannot activate: The room is already occupied during this time period.',
                    ], 422);
                }
            }
        }

        $timetable->status = $targetStatus;
        $timetable->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Timetable status updated to {$timetable->status}",
            'data'    => [
                'timetable' => [
                    'id'         => $timetable->id,
                    'status'     => $timetable->status,
                    'updated_at' => $timetable->updated_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function getTimetableWithAttendanceByDate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $targetDateStr = $request->query('date');
            $parsedDate = Carbon::createFromFormat('Y-m-d', $targetDateStr);
            $dayName = $parsedDate->format('l');

            $timetables = Timetable::with([
                'sectionAssignments.subject',
                'sectionAssignments.section',
                'sectionAssignments.lecturer.user',
                'attendances' => function ($query) use ($targetDateStr) {
                    $query->where('date', $targetDateStr)->with('user');
                }
            ])
            ->where('day_of_week', $dayName)
            ->where('status', 'active')
            ->get();

            $formattedData = $timetables->map(function ($timetable) {
                return [
                    'timetable_id' => $timetable->id,
                    'day_of_week'  => $timetable->day_of_week,
                    'time_slot'    => $timetable->start_time . ' - ' . $timetable->end_time,
                    'room_number'  => $timetable->room_number,
                    'link'         => $timetable->link,
                    'subject'      => $timetable->sectionAssignments->subject->name,
                    'lecturer'     => $timetable->sectionAssignments->lecturer->user->name,
                    'section'      => $timetable->sectionAssignments->section->name,
                    'attendance_logs' => $timetable->attendances->map(function ($attendance) {
                        return [
                            'attendance_id' => $attendance->id,
                            'user_id'       => $attendance->user_id,
                            'student_name'  => $attendance->user->name,
                            'status'        => $attendance->status,
                            'remark'        => $attendance->remark,
                            'logged_at'     => $attendance->created_at ? $attendance->created_at->format('Y-m-d H:i:s') : null,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status'  => 'success',
                'message' => "Timetable schedule and attendance logs retrieved for {$targetDateStr} ({$dayName})",
                'data'    => [
                    'date'       => $targetDateStr,
                    'day'        => $dayName,
                    'timetables' => $formattedData
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not compile data sheet for requested date.'
            ], 500);
        }
    }
}
