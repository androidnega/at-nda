<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentPasswordResetService;
use App\Support\SchemaFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class StudentPasswordResetController extends Controller
{
    public function __construct(private StudentPasswordResetService $service)
    {
    }

    public function requestForm(Request $request): View
    {
        $featureAvailable = SchemaFeatures::hasStudentsEmail()
            && SchemaFeatures::hasPasswordResetCodes();

        return view('student.password-reset.request', [
            'featureAvailable' => $featureAvailable,
            'indexNumber' => $request->session()->get('pwd_reset_index') ?? old('index_number'),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string|max:100',
        ]);

        $indexNumber = strtoupper(trim($validated['index_number']));

        // Crude per-IP throttle so we don't get used as a spam relay.
        $throttleKey = 'pwd-reset|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            return back()
                ->withInput()
                ->with('error', 'Too many reset requests from this device. Try again in a few minutes.');
        }
        RateLimiter::hit($throttleKey, 600);

        $student = Student::query()->where('index_number', $indexNumber)->first();

        // Always respond the same way so a stranger can't probe which index
        // numbers are real. Only surface an error if it's an environment
        // problem the student can't fix themselves.
        if ($student) {
            $error = $this->service->issueCode($student, $request);
            if ($error !== null) {
                return back()->withInput()->with('error', $error);
            }
        }

        $request->session()->put('pwd_reset_index', $indexNumber);

        return redirect()
            ->route('student.password.verify.form')
            ->with('success', 'If that index number is registered with an email, we just sent a 6-digit code. It expires in '.StudentPasswordResetService::CODE_TTL_MINUTES.' minutes.');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $index = $request->session()->get('pwd_reset_index') ?? old('index_number');
        if (! $index) {
            return redirect()->route('student.password.request.form');
        }

        return view('student.password-reset.verify', [
            'indexNumber' => $index,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'index_number' => 'required|string|max:100',
            'code' => 'required|string|min:6|max:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $indexNumber = strtoupper(trim($validated['index_number']));

        $error = $this->service->consumeCode($indexNumber, $validated['code'], $validated['password']);
        if ($error !== null) {
            return back()->withInput()->with('error', $error);
        }

        $request->session()->forget('pwd_reset_index');

        return redirect()
            ->route('home')
            ->with('success', 'Your password has been reset. Sign in with your new password.');
    }
}
