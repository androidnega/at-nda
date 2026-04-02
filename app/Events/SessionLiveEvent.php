<?php

namespace App\Events;

use App\Models\AttendanceSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Live updates for attendance sessions (QR stats, dashboards) over WebSockets (Reverb).
 */
class SessionLiveEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AttendanceSession $session,
        public string $action,
        public array $payload = [],
    ) {
        $this->session->loadMissing('course');
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('attendance.session.'.$this->session->id),
        ];

        $classId = $this->session->course?->class_id;
        if ($classId) {
            $channels[] = new Channel('class.'.$classId.'.attendance');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'session.live';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $course = $this->session->course;

        return [
            'action' => $this->action,
            'session_id' => $this->session->id,
            'course_id' => $course?->id,
            'class_id' => $course?->class_id,
            'is_active' => (bool) $this->session->is_active,
            'mode' => $this->session->mode,
            'present_count' => $this->payload['present_count'] ?? null,
            'expires_at' => $this->session->expires_at?->toIso8601String(),
        ];
    }
}
