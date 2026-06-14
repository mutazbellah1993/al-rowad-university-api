<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentCourseRegistration\StoreStudentCourseRegistrationRequest;
use App\Http\Requests\StudentCourseRegistration\UpdateStudentCourseRegistrationRequest;
use App\Http\Resources\StudentCourseRegistrationResource;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\StudentCourseRegistration;
use App\Services\RegistrationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    protected function successResponse(mixed $data = [], string $message = 'Operation completed successfully', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function errorResponse(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public function index(RegistrationService $service): JsonResponse
    {
        $registrations = $service->paginate(request()->integer('per_page', 15));

        return $this->successResponse(StudentCourseRegistrationResource::collection($registrations)->response(request())->getData(true));
    }

    public function show(StudentCourseRegistration $registration): JsonResponse
    {
        $registration->load(['student', 'courseOffering.course', 'courseOffering.academicYear', 'courseOffering.semester', 'registrationStatus', 'resultStatus']);

        return $this->successResponse((new StudentCourseRegistrationResource($registration))->resolve(request()));
    }

    public function store(StoreStudentCourseRegistrationRequest $request, RegistrationService $service): JsonResponse
    {
        $registration = $service->register($request->validated());

        return $this->successResponse((new StudentCourseRegistrationResource($registration))->resolve(request()), 'Operation completed successfully', 201);
    }

    public function update(StudentCourseRegistration $registration, UpdateStudentCourseRegistrationRequest $request, RegistrationService $service): JsonResponse
    {
        $updatedRegistration = $service->updateRegistration($registration, $request->validated());

        return $this->successResponse((new StudentCourseRegistrationResource($updatedRegistration))->resolve(request()));
    }

    public function destroy(StudentCourseRegistration $registration): JsonResponse
    {
        $registration->delete();

        return $this->successResponse([], 'Operation completed successfully');
    }

    public function studentsByCourseOffering(CourseOffering $course_offering, RegistrationService $service): JsonResponse
    {
        $registrations = $service->registrationsForCourseOffering($course_offering, request()->integer('per_page', 15));

        return $this->successResponse(StudentCourseRegistrationResource::collection($registrations)->response(request())->getData(true));
    }

    public function registrationsByStudent(Student $student, RegistrationService $service): JsonResponse
    {
        $registrations = $service->registrationsForStudent($student, request()->integer('per_page', 15));

        return $this->successResponse(StudentCourseRegistrationResource::collection($registrations)->response(request())->getData(true));
    }
}