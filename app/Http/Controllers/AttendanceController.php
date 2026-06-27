<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Timetable;
use App\Models\Student;
use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Attendance::query()->with([
            'user', 
            'timetable.sectionAssignments.subject',
            'timetable.sectionAssignments.section',
            'timetable.sectionAssignments.lecturer.user'
        ]);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->get()->map(function ($item) {
            $assignment = $item->timetable->sectionAssignments;
            
            return [
                'id'            => $item->id,
                'user_id'       => $item->user_id,
                'user_name'     => $item->user->name,
                'timetable_id'  => $item->timetable_id,
                'subject_code'  => $assignment->subject->code,
                'section_code'  => $assignment->section->code,
                'lecturer_name' => $assignment->lecturer->user->name,
                'day_of_week'   => $item->timetable->day_of_week,
                'time_slot'     => $item->timetable->start_time . ' - ' . $item->timetable->end_time,
                'date'          => $item->date,
                'status'        => $item->status,
                'remark'        => $item->remark,
                'created_at'    => $item->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Attendances retrieved successfully',
            'data'    => [
                'attendances' => $attendances
            ]
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id'      => 'required|exists:users,id',
            'timetable_id' => 'required|exists:timetables,id',
            'created_at'   => 'nullable|date_format:Y-m-d H:i:s',
            'remark'       => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            $userId = $request->user_id;
            $timetableId = $request->timetable_id;

            $student = Student::where('user_id', $userId)->first();
            $lecturer = Lecturer::where('user_id', $userId)->first();

            if (!$student && !$lecturer) {
                $validator->errors()->add('user_id', 'The selected user must have either an active student or lecturer profile to register attendance.');
                return;
            }

            if ($timetableId) {
                $timetable = Timetable::with('sectionAssignments')->find($timetableId);
                if (!$timetable) return;

                $checkInTime = $request->has('created_at') 
                    ? Carbon::createFromFormat('Y-m-d H:i:s', $request->created_at) 
                    : Carbon::now();

                $realTodayStr = Carbon::now()->toDateString();
                if ($checkInTime->toDateString() !== $realTodayStr) {
                    $validator->errors()->add('created_at', "Date Error: You can only register attendance for today's active date ({$realTodayStr}). Past or future dates are rejected.");
                    return;
                }

                $incomingDay = $checkInTime->format('l'); 
                if (strcasecmp($incomingDay, $timetable->day_of_week) !== 0) {
                    $validator->errors()->add('created_at', "Invalid Day: This class is scheduled for {$timetable->day_of_week}s, but you are checking in on a {$incomingDay}.");
                    return;
                }

                $classStartTime = Carbon::parse($checkInTime->toDateString() . ' ' . $timetable->start_time);
                $minutesDiff = $classStartTime->diffInMinutes($checkInTime, false);

                if ($minutesDiff < -30) {
                    $validator->errors()->add('created_at', 'You are attempting to check-in too early. Portal opens 30 minutes before class starts.');
                    return;
                }

                $assignment = $timetable->sectionAssignments;
                if ($assignment) {
                    if ($student) {
                        $targetSectionId = $assignment->section_id;
                        
                        $isEnrolledInSection = DB::table('enrolments')
                            ->where('student_id', $student->id)
                            ->where('section_id', $targetSectionId)
                            ->where('status', 'active')
                            ->exists();

                        if (!$isEnrolledInSection) {
                            $validator->errors()->add('user_id', 'Enrollment Error: This student is not registered in the Section/Batch assigned to this class.');
                            return;
                        }
                    } elseif ($lecturer) {
                        if ($assignment->lecturer_id != $lecturer->id) {
                            $validator->errors()->add('user_id', 'Lecturer Error: You are not scheduled to teach this specific section assignment session.');
                            return;
                        }
                    }
                }

                $targetDate = $checkInTime->toDateString();
                $exists = Attendance::where('user_id', $userId)
                    ->where('timetable_id', $timetableId)
                    ->where('date', $targetDate)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('timetable_id', 'Attendance for this user has already been logged for this class today.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $timetable = Timetable::findOrFail($request->timetable_id);
            
            $checkInTime = $request->has('created_at') 
                ? Carbon::createFromFormat('Y-m-d H:i:s', $request->created_at) 
                : Carbon::now();

            $classStartTime = Carbon::parse($checkInTime->toDateString() . ' ' . $timetable->start_time);
            $minutesDiff = $classStartTime->diffInMinutes($checkInTime, false);

            if ($minutesDiff <= 15) {
                $automaticStatus = 'present';
            } elseif ($minutesDiff > 15 && $minutesDiff <= 45) {
                $automaticStatus = 'late';
            } else {
                $automaticStatus = 'absent';
            }

            if ($request->input('status') === 'excused') {
                $automaticStatus = 'excused';
            }

            $attendance = new Attendance();
            $attendance->user_id = $request->user_id;
            $attendance->timetable_id = $request->timetable_id;
            $attendance->date = $checkInTime->toDateString();
            $attendance->status = $automaticStatus;
            $attendance->remark = $request->remark;
            
            if ($request->has('created_at')) {
                $attendance->created_at = $checkInTime;
            }
            
            $attendance->save();
            $attendance->load(['user', 'timetable']);

            return response()->json([
                'status'  => 'success',
                'message' => 'Attendance logged successfully with auto-calculated status',
                'data'    => [
                    'attendance' => [
                        'id'           => $attendance->id,
                        'user_id'      => $attendance->user_id,
                        'user_name'    => $attendance->user->name,
                        'timetable_id' => $attendance->timetable_id,
                        'date'         => $attendance->date,
                        'status'       => $attendance->status,
                        'remark'       => $attendance->remark,
                        'created_at'   => $attendance->created_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not process attendance entry.'
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $attendance = Attendance::with([
            'user', 
            'timetable.sectionAssignments.subject',
            'timetable.sectionAssignments.section',
            'timetable.sectionAssignments.lecturer.user'
        ])->find($id);

        if (!$attendance) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Attendance record not found'
            ], 404);
        }

        $assignment = $attendance->timetable->sectionAssignments;

        return response()->json([
            'status'  => 'success',
            'message' => 'Attendance details retrieved',
            'data'    => [
                'attendance' => [
                    'id'            => $attendance->id,
                    'user_id'       => $attendance->user_id,
                    'user_name'     => $attendance->user->name,
                    'timetable_id'  => $attendance->timetable_id,
                    'subject_code'  => $assignment->subject->code,
                    'section_code'  => $assignment->section->code,
                    'lecturer_name' => $assignment->lecturer->user->name,
                    'day_of_week'   => $attendance->timetable->day_of_week,
                    'time_slot'     => $attendance->timetable->start_time . ' - ' . $attendance->timetable->end_time,
                    'date'          => $attendance->date,
                    'status'        => $attendance->status,
                    'remark'        => $attendance->remark,
                    'created_at'    => $attendance->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $attendance = Attendance::find($id);

        if (!$attendance) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Attendance record not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id'      => 'nullable|exists:users,id',
            'timetable_id' => 'nullable|exists:timetables,id',
            'status'       => 'in:present,absent,late,excused',
            'created_at'   => 'nullable|date_format:Y-m-d H:i:s',
            'remark'       => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $attendance) {
            $userId = $request->input('user_id', $attendance->user_id);
            $timetableId = $request->input('timetable_id', $attendance->timetable_id);

            $student = Student::where('user_id', $userId)->first();
            $lecturer = Lecturer::where('user_id', $userId)->first();

            if ($request->has('user_id') && !$student && !$lecturer) {
                $validator->errors()->add('user_id', 'The updated user must have either an active student or lecturer profile.');
                return;
            }

            $checkTimeStr = $request->input('created_at', $attendance->created_at->format('Y-m-d H:i:s'));
            $checkInTime = Carbon::createFromFormat('Y-m-d H:i:s', $checkTimeStr);
            $targetTimetable = Timetable::with('sectionAssignments')->find($timetableId);
            
            if ($targetTimetable) {
                $incomingDay = $checkInTime->format('l');
                if (strcasecmp($incomingDay, $targetTimetable->day_of_week) !== 0) {
                    $validator->errors()->add('created_at', "Invalid Day: This class belongs on a {$targetTimetable->day_of_week}, your input target is a {$incomingDay}.");
                    return;
                }

                $assignment = $targetTimetable->sectionAssignments;
                if ($assignment) {
                    if ($student) {
                        $targetSectionId = $assignment->section_id;

                        $isEnrolledInSection = DB::table('enrolments')
                            ->where('student_id', $student->id)
                            ->where('section_id', $targetSectionId)
                            ->where('status', 'active')
                            ->exists();

                        if (!$isEnrolledInSection) {
                            $validator->errors()->add('user_id', 'Enrollment Error: Student is not registered for the targeted section segment.');
                            return;
                        }
                    } elseif ($lecturer && $assignment->lecturer_id != $lecturer->id) {
                        $validator->errors()->add('user_id', 'Lecturer Error: Assigned Lecturer profile clash.');
                        return;
                    }
                }
            }

            if ($request->has('user_id') || $request->has('timetable_id') || $request->has('created_at')) {
                $targetDate = $checkInTime->toDateString(); 

                $duplicateExists = Attendance::where('user_id', $userId)
                    ->where('timetable_id', $timetableId)
                    ->where('date', $targetDate)
                    ->where('id', '!=', $attendance->id)
                    ->exists();

                if ($duplicateExists) {
                    $validator->errors()->add('timetable_id', 'Attendance for this user has already been logged for this class today.');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $attendance->update($request->only(['user_id', 'timetable_id', 'status', 'remark', 'created_at']));
        
        if ($request->has('created_at') && $request->filled('created_at')) {
            $attendance->date = Carbon::parse($request->created_at)->toDateString();
            $attendance->save();
        }

        $attendance->load(['user', 'timetable']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Attendance record updated successfully',
            'data'    => [
                'attendance' => [
                    'id'           => $attendance->id,
                    'user_id'      => $attendance->user_id,
                    'user_name'    => $attendance->user->name,
                    'timetable_id' => $attendance->timetable_id,
                    'date'         => $attendance->date,
                    'status'       => $attendance->status,
                    'remark'       => $attendance->remark,
                    'created_at'   => $attendance->created_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $attendance = Attendance::with(['user', 'timetable'])->find($id);

        if (!$attendance) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Attendance record not found'
            ], 404);
        }
        
        $statusCycle = [
            'absent'  => 'present',
            'present' => 'late',
            'late'    => 'excused',
            'excused' => 'absent',
        ];

        $newStatus = $statusCycle[$attendance->status] ?? 'present';
        $attendance->status = $newStatus;

        if ($newStatus === 'excused' && $request->has('remark')) {
            $attendance->remark = $request->input('remark');
        } else {
            $attendance->remark = "Status manually changed to {$newStatus}.";
        }

        $attendance->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Attendance status rotated to {$attendance->status}",
            'data'    => [
                'attendance' => [
                    'id'           => $attendance->id,
                    'user_id'      => $attendance->user_id,
                    'user_name'    => $attendance->user->name,
                    'timetable_id' => $attendance->timetable_id,
                    'date'         => $attendance->date,
                    'status'       => $attendance->status,
                    'remark'       => $attendance->remark,
                    'updated_at'   => $attendance->updated_at->format('Y-m-d H:i:s'),
                ]
            ]
        ], 200);
    }

    public function refreshAbsences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timetable_id' => 'required|exists:timetables,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $timetableId = $request->timetable_id;
            $today = Carbon::now();
            $dayName = $today->format('l'); 
            $dateStr = $today->toDateString();

            $timetable = Timetable::with('sectionAssignments')->find($timetableId);

            if (strcasecmp($dayName, $timetable->day_of_week) !== 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Calendar Day Mismatch: You can only refresh active sessions scheduled for today ({$dayName}). This slot is for {$timetable->day_of_week}s."
                ], 400);
            }

            $assignment = $timetable->sectionAssignments;
            if (!$assignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class config profile layout missing for this session.'
                ], 422);
            }

            $enrolledStudentIds = DB::table('enrolments')
                ->where('section_id', $assignment->section_id)
                ->where('status', 'active')
                ->pluck('student_id');

            $enrolledStudents = Student::whereIn('id', $enrolledStudentIds)->get();

            $absencesLogged = 0;

            foreach ($enrolledStudents as $student) {
                $hasLogged = Attendance::where('user_id', $student->user_id)
                    ->where('timetable_id', $timetableId)
                    ->where('date', $dateStr)
                    ->exists();

                if (!$hasLogged) {
                    $absenceRow = new Attendance();
                    $absenceRow->user_id = $student->user_id;
                    $absenceRow->timetable_id = $timetableId;
                    $absenceRow->date = $dateStr;
                    $absenceRow->status = 'absent';
                    $absenceRow->remark = 'Auto-filled via Lecturer Dashboard';
                    $absenceRow->save();

                    $absencesLogged++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance sheet evaluated successfully.',
                'data' => [
                    'timetable_id' => (int)$timetableId,
                    'date' => $dateStr,
                    'no_shows_auto_filled' => $absencesLogged
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server Error: Could not compute sheet auto-fill.'
            ], 500);
        }
    }
}