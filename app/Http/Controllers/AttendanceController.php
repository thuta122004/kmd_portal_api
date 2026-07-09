<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Enrolment;
use App\Models\Timetable;
use App\Models\Student;
use App\Models\Section;
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

            $timetable = Timetable::with('sectionAssignments.section')->find($timetableId);
            if (!$timetable) {
                $validator->errors()->add('timetable_id', 'Timetable not found.');
                return;
            }

            $checkInTime = $request->has('created_at') 
                ? Carbon::createFromFormat('Y-m-d H:i:s', $request->created_at) 
                : Carbon::now();

            $section = $timetable->sectionAssignments->section ?? null;
            if ($section) {
                $startDate = Carbon::parse($section->start_date);
                $endDate = Carbon::parse($section->end_date);

                if ($checkInTime->lt($startDate) || $checkInTime->gt($endDate)) {
                    $validator->errors()->add('created_at', "Date Error: Attendance can only be logged between {$section->start_date} and {$section->end_date}.");
                    return;
                }
            }

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
            $timetable = Timetable::find($request->timetable_id);
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
            return response()->json(['status' => 'error', 'message' => 'Record not found'], 404);
        }

        $request->validate([
            'status' => 'required|in:present,absent,late,excused',
            'remark' => 'nullable|string',
        ]);

        $attendance->status = $request->status;
        $attendance->remark = $request->remark;
        $attendance->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Status updated successfully',
            'data' => ['attendance' => $attendance]
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
        try {
            $startDate = $request->has('start_date') ? Carbon::parse($request->start_date) : Carbon::now();
            $today = Carbon::now();

            if ($startDate->diffInDays($today) > 30) {
                return response()->json(['status' => 'error', 'message' => 'Cannot refresh more than 30 days at once.'], 422);
            }

            $totalAbsencesLogged = 0;

            for ($date = $startDate; $date->lte($today); $date->addDay()) {
                
                $dateString = $date->toDateString();
                $dayName = $date->format('l');

                $timetables = Timetable::where('day_of_week', $dayName)
                    ->where('status', 'active')
                    ->with('sectionAssignments.section', 'sectionAssignments.lecturer.user')
                    ->get();

                foreach ($timetables as $timetable) {
                    $assignment = $timetable->sectionAssignments;
                    if (!$assignment || !$assignment->section) continue;

                    $section = $assignment->section;
                    if ($date->lt(Carbon::parse($section->start_date)) || $date->gt(Carbon::parse($section->end_date))) {
                        continue;
                    }

                    if ($date->isToday() && Carbon::parse($timetable->start_time)->gt($today)) {
                        continue;
                    }

                    $existingUserIds = Attendance::where('timetable_id', $timetable->id)
                        ->where('date', $dateString)
                        ->pluck('user_id')
                        ->toArray();

                    $studentIds = DB::table('enrolments')
                        ->where('section_id', $assignment->section_id)
                        ->where('status', 'active')
                        ->pluck('student_id');

                    $enrolledStudents = Student::whereIn('id', $studentIds)->get();

                    foreach ($enrolledStudents as $student) {
                        if (!in_array($student->user_id, $existingUserIds)) {
                            Attendance::create([
                                'user_id'      => $student->user_id,
                                'timetable_id' => $timetable->id,
                                'date'         => $dateString,
                                'status'       => 'absent',
                                'remark'       => 'Auto-filled via Global Refresh'
                            ]);
                            $totalAbsencesLogged++;
                        }
                    }

                    if ($assignment->lecturer && $assignment->lecturer->user) {
                        $lUserId = $assignment->lecturer->user->id;
                        if (!in_array($lUserId, $existingUserIds)) {
                            Attendance::create([
                                'user_id'      => $lUserId,
                                'timetable_id' => $timetable->id,
                                'date'         => $dateString,
                                'status'       => 'absent',
                                'remark'       => 'Auto-filled via Global Refresh'
                            ]);
                            $totalAbsencesLogged++;
                        }
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance evaluation completed from ' . $startDate->toDateString() . ' to ' . $today->toDateString(),
                'data' => ['total_no_shows_auto_filled' => $totalAbsencesLogged]
            ], 200);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function logAttendanceIfMissing($userId, $timetableId, $date)
    {
        $hasLogged = Attendance::where('user_id', $userId)
            ->where('timetable_id', $timetableId)
            ->where('date', $date)
            ->exists();

        if (!$hasLogged) {
            Attendance::create([
                'user_id'      => $userId,
                'timetable_id' => $timetableId,
                'date'         => $date,
                'status'       => 'absent',
                'remark'       => 'Auto-filled via Global Refresh'
            ]);
            return 1;
        }
        return 0;
    }

    public function getStudentAttendanceReport($studentId, $sectionId): JsonResponse
    {
        $student = Student::with('user')->find($studentId);
        $section = Section::find($sectionId);

        if (!$student || !$section) {
            return response()->json(['status' => 'error', 'message' => 'Student or Section not found'], 404);
        }

        $allAttendanceRecords = Attendance::where('user_id', $student->user_id)
            ->whereHas('timetable.sectionAssignments', function ($query) use ($sectionId) {
                $query->where('section_id', $sectionId);
            })
            ->get();

        $totalClasses = $allAttendanceRecords->count();
        $attendedClasses = $allAttendanceRecords->whereIn('status', ['present', 'excused'])->count();
        
        $percentage = ($totalClasses > 0) ? ($attendedClasses / $totalClasses) * 100 : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_name' => $student->user->name,
                'section_name' => $section->name,
                'percentage'   => round($percentage, 2),
                'attended'     => $attendedClasses,
                'total'        => $totalClasses
            ]
        ]);
    }

    public function getSectionAttendanceSummary($sectionId): JsonResponse
    {
        $enrolments = Enrolment::where('section_id', $sectionId)
            ->with('student.user')
            ->get();

        $allAttendance = Attendance::whereHas('timetable.sectionAssignments', function ($query) use ($sectionId) {
            $query->where('section_id', $sectionId);
        })->get()->groupBy('user_id');

        $reportList = [];
        $distribution = ['0-25%' => 0, '26-50%' => 0, '51-75%' => 0, '76-100%' => 0];

        foreach ($enrolments as $enrolment) {
            $student = $enrolment->student;
            $studentRecords = $allAttendance->get($student->user_id, collect());
            
            $totalClasses = $studentRecords->count();
            $attendedClasses = $studentRecords->whereIn('status', ['present', 'excused'])->count();
            
            $percentage = ($totalClasses > 0) ? ($attendedClasses / $totalClasses) * 100 : 0;

            $reportList[] = [
                'name'       => $student->user->name,
                'reg_number' => $student->student_reg_number,
                'attended'   => $attendedClasses,
                'total'      => $totalClasses,
                'percentage' => round($percentage, 2)
            ];

            if ($percentage <= 25) $distribution['0-25%']++;
            elseif ($percentage <= 50) $distribution['26-50%']++;
            elseif ($percentage <= 75) $distribution['51-75%']++;
            else $distribution['76-100%']++;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'list'         => $reportList,
                'distribution' => $distribution,
                'overall_avg'  => count($reportList) > 0 ? round(collect($reportList)->avg('percentage'), 2) : 0
            ]
        ]);
    }

    public function getStudentSubjectAttendanceReport($sectionId): JsonResponse
    {
        $enrolments = Enrolment::where('section_id', $sectionId)
            ->with('student.user')
            ->get();

        $attendanceRecords = Attendance::whereHas('timetable.sectionAssignments', function ($query) use ($sectionId) {
            $query->where('section_id', $sectionId);
        })
        ->with(['timetable.sectionAssignments.subject'])
        ->get();

        $report = [];

        foreach ($enrolments as $enrolment) {
            $student = $enrolment->student;
            
            $studentAttendance = $attendanceRecords->where('user_id', $student->user_id);
            
            $attendanceBySubject = $studentAttendance->groupBy(function ($item) {
                return $item->timetable->sectionAssignments->subject->name;
            });

            $subjectsData = [];

            foreach ($attendanceBySubject as $subjectName => $records) {
                $total = $records->count();
                $attended = $records->whereIn('status', ['present', 'excused'])->count();
                $percentage = ($total > 0) ? ($attended / $total) * 100 : 0;

                $subjectsData[] = [
                    'subject_name' => $subjectName,
                    'attended'     => $attended,
                    'total'        => $total,
                    'percentage'   => round($percentage)
                ];
            }

            $report[] = [
                'student_name' => $student->user->name,
                'reg_number'   => $student->student_reg_number,
                'subjects'     => $subjectsData
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => $report
        ], 200);
    }
}