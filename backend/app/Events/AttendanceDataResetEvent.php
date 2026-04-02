<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after admin resets weeks/sessions/attendance so mobile (Echo) and API clients can resync.
 */
class AttendanceDataResetEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $courseIds
     * @param  array<int, int>  $classIds
     */
    public function __construct(
        public array $courseIds,
        public array $classIds,
        public int $version,
        public string $scope,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('app.attendance.sync')];
        foreach ($this->classIds as $cid) {
            $channels[] = new Channel('class.'.(int) $cid.'.sync');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'attendance.data_reset';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'scope' => $this->scope,
            'attendance_data_version' => $this->version,
            'course_ids' => array_values(array_map('intval', $this->courseIds)),
            'class_ids' => array_values(array_map('intval', $this->classIds)),
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
