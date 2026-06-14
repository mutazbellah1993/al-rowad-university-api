<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\RegistrationStatus;
use App\Models\Student;
use App\Models\StudentCourseRegistration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return StudentCourseRegistration::query()
            ->with(['student', 'courseOffering.course', 'courseOffering.academicYear', 'courseOffering.semester', 'registrationStatus', 'resultStatus'])
            ->latest('student_course_registration_id')
            ->paginate($perPage);
    }

    public function findOrFail(int $registrationId): StudentCourseRegistration
    {
        return StudentCourseRegistration::query()
            ->with(['student', 'courseOffering.course', 'courseOffering.academicYear', 'courseOffering.semester', 'registrationStatus', 'resultStatus'])
            ->findOrFail($registrationId);
    }

    public function register(array $data): StudentCourseRegistration
    {
        return DB::transaction(function () use ($data): StudentCourseRegistration {
            $student = Student::query()->find($data['student_id']);
            if (! $student) {
                throw ValidationException::withMessages([
                    'student_id' => ['The selected student does not exist.'],
                ]);
            }

            $courseOffering = CourseOffering::query()->find($data['course_offering_id']);
            if (! $courseOffering) {
                throw ValidationException::withMessages([
                    'course_offering_id' => ['The selected course offering does not exist.'],
                ]);
            }

            if ($courseOffering->status !== 'open') {
                throw ValidationException::withMessages([
                    'course_offering_id' => ['The selected course offering is not open for registration.'],
                ]);
            }

            if ((int) $courseOffering->available_seats <= 0) {
                throw ValidationException::withMessages([
                    'course_offering_id' => ['No available seats remain for the selected course offering.'],
                ]);
            }

            $alreadyRegistered = StudentCourseRegistration::query()
                ->where('student_id', $student->student_id)
                ->where('course_offering_id', $courseOffering->course_offering_id)
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages([
                    'student_id' => ['This student is already registered in the selected course offering.'],
                ]);
            }

            $registeredStatusId = RegistrationStatus::query()
                ->where('status_code', 'registered')
                ->value('registration_status_id');

            if (! $registeredStatusId) {
                throw new ModelNotFoundException('Registration status "registered" was not found.');
            }

            $registration = StudentCourseRegistration::query()->create([
                'student_id' => $student->student_id,
                'course_offering_id' => $courseOffering->course_offering_id,
                'registration_date' => $data['registration_date'] ?? now()->toDateString(),
                'registered_by_user_id' => $data['registered_by_user_id'],
                'advisor_user_id' => $data['advisor_user_id'] ?? null,
                'registration_status_id' => $data['registration_status_id'] ?? $registeredStatusId,
                'result_status_id' => $data['result_status_id'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $courseOffering->decrement('available_seats');

            return $registration->load(['student', 'courseOffering.course', 'courseOffering.academicYear', 'courseOffering.semester', 'registrationStatus', 'resultStatus']);
        });
    }

    public function updateRegistration(StudentCourseRegistration $registration, array $data): StudentCourseRegistration
    {
        $registration->update($data);

        return $registration->fresh()->load(['student', 'courseOffering.course', 'courseOffering.academicYear', 'courseOffering.semester', 'registrationStatus', 'resultStatus']);
    }

    public function registrationsForStudent(Student $student, int $perPage = 15): LengthAwarePaginator
    {
        return StudentCourseRegistration::query()
            ->with(['courseOffering.course', 'courseOffering.academicYear', 'courseOffering.semester', 'registrationStatus', 'resultStatus'])
            ->where('student_id', $student->student_id)
            ->latest('student_course_registration_id')
            ->paginate($perPage);
    }

    public function registrationsForCourseOffering(CourseOffering $courseOffering, int $perPage = 15): LengthAwarePaginator
    {
        return StudentCourseRegistration::query()
            ->with(['student', 'registrationStatus', 'resultStatus'])
            ->where('course_offering_id', $courseOffering->course_offering_id)
            ->latest('student_course_registration_id')
            ->paginate($perPage);
    }
}