<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\LoggedSms;
use App\Models\LoggedWhatsappMessage;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Admin-only review of communication logs (PII — keep behind admin.only).
 */
class AdminCommunicationLogController extends Controller
{
    public function sms(Request $request): View
    {
        $query = LoggedSms::query()
            ->with(['student:id,index_number,first_name,last_name']);

        $this->applySmsFilters($query, $request);

        $sort = (string) $request->query('sort', 'occurred_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['occurred_at', 'direction', 'delivery_status', 'index_number', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'occurred_at';
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        $logs = $query->paginate(40)->withQueryString();

        return view('admin.communication_logs.sms', compact('logs'));
    }

    public function calls(Request $request): View
    {
        $query = CallLog::query()
            ->with(['student:id,index_number,first_name,last_name']);

        $this->applyCallFilters($query, $request);

        $sort = (string) $request->query('sort', 'occurred_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['occurred_at', 'direction', 'call_outcome', 'index_number', 'duration_seconds', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'occurred_at';
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        $logs = $query->paginate(40)->withQueryString();

        return view('admin.communication_logs.calls', compact('logs'));
    }

    public function exportSmsCsv(Request $request): StreamedResponse
    {
        $filename = 'sms-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['occurred_at', 'index_number', 'direction', 'delivery_status', 'peer_number', 'body_preview', 'device_id', 'client_record_id']);
            $this->filteredSmsQuery($request)
                ->orderByDesc('occurred_at')
                ->chunkById(400, function (Collection $rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            optional($row->occurred_at)->toIso8601String(),
                            $row->index_number,
                            $row->direction,
                            $row->delivery_status,
                            $row->peer_number,
                            $row->body_preview,
                            $row->device_id,
                            $row->client_record_id,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadSmsZip(Request $request)
    {
        if (! class_exists(ZipArchive::class)) {
            return redirect()->back()->with('error', 'ZIP extension is not available on this server.');
        }

        $rows = $this->filteredSmsQuery($request)
            ->orderByDesc('occurred_at')
            ->limit(20000)
            ->get();

        $csv = $this->renderSmsCsv($rows);
        $zipPath = storage_path('app/tmp/sms-logs-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0775, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Could not build ZIP archive.');
        }

        $zip->addFromString('sms-logs.csv', $csv);
        $zip->addFromString('README.txt', "Export generated at ".now()->toDateTimeString()."\nRows included: ".$rows->count()."\n");
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function purgeSms(Request $request)
    {
        $deleted = $this->filteredSmsQuery($request)->delete();

        return redirect()
            ->route('dashboard.communication-logs.sms.index')
            ->with('success', "Deleted {$deleted} SMS log records.");
    }

    public function deleteSingleSms(LoggedSms $message): RedirectResponse
    {
        $message->delete();

        return redirect()
            ->route('dashboard.communication-logs.sms.index')
            ->with('success', 'Deleted 1 SMS log record.');
    }

    public function smsBulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,export_csv,export_zip',
            'selected_ids' => 'required|array|min:1',
            'selected_ids.*' => 'integer|min:1',
        ]);
        $request->merge(['selected_ids' => $this->selectedIds($validated['selected_ids'])]);

        if (($request->input('selected_ids') ?? []) === []) {
            return redirect()->route('dashboard.communication-logs.sms.index')->with('error', 'Select at least one message.');
        }

        return match ($validated['action']) {
            'delete' => $this->purgeSms($request),
            'export_zip' => $this->downloadSmsZip($request),
            default => $this->exportSmsCsv($request),
        };
    }

    public function exportCallCsv(Request $request): StreamedResponse
    {
        $filename = 'call-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['occurred_at', 'index_number', 'direction', 'call_outcome', 'duration_seconds', 'peer_number', 'device_id', 'client_record_id']);
            $this->filteredCallQuery($request)
                ->orderByDesc('occurred_at')
                ->chunkById(400, function (Collection $rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            optional($row->occurred_at)->toIso8601String(),
                            $row->index_number,
                            $row->direction,
                            $row->call_outcome,
                            $row->duration_seconds,
                            $row->peer_number,
                            $row->device_id,
                            $row->client_record_id,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadCallZip(Request $request)
    {
        if (! class_exists(ZipArchive::class)) {
            return redirect()->back()->with('error', 'ZIP extension is not available on this server.');
        }

        $rows = $this->filteredCallQuery($request)
            ->orderByDesc('occurred_at')
            ->limit(20000)
            ->get();
        $csv = $this->renderCallCsv($rows);
        $zipPath = storage_path('app/tmp/call-logs-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0775, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Could not build ZIP archive.');
        }
        $zip->addFromString('call-logs.csv', $csv);
        $zip->addFromString('README.txt', "Export generated at ".now()->toDateTimeString()."\nRows included: ".$rows->count()."\n");
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function purgeCalls(Request $request)
    {
        $deleted = $this->filteredCallQuery($request)->delete();

        return redirect()
            ->route('dashboard.communication-logs.calls.index')
            ->with('success', "Deleted {$deleted} call log records.");
    }

    public function deleteSingleCall(CallLog $message): RedirectResponse
    {
        $message->delete();

        return redirect()
            ->route('dashboard.communication-logs.calls.index')
            ->with('success', 'Deleted 1 call log record.');
    }

    public function callsBulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,export_csv,export_zip',
            'selected_ids' => 'required|array|min:1',
            'selected_ids.*' => 'integer|min:1',
        ]);
        $request->merge(['selected_ids' => $this->selectedIds($validated['selected_ids'])]);

        if (($request->input('selected_ids') ?? []) === []) {
            return redirect()->route('dashboard.communication-logs.calls.index')->with('error', 'Select at least one message.');
        }

        return match ($validated['action']) {
            'delete' => $this->purgeCalls($request),
            'export_zip' => $this->downloadCallZip($request),
            default => $this->exportCallCsv($request),
        };
    }

    public function whatsapp(Request $request): View
    {
        $query = LoggedWhatsappMessage::query()
            ->with(['student:id,index_number,first_name,last_name']);

        $this->applyWhatsappFilters($query, $request);

        $sort = (string) $request->query('sort', 'occurred_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['occurred_at', 'source_app', 'index_number', 'created_at'];
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'occurred_at';
            $dir = 'desc';
        }
        $query->orderBy($sort, $dir);

        $logs = $query->paginate(40)->withQueryString();

        return view('admin.communication_logs.whatsapp', compact('logs'));
    }

    public function exportWhatsappCsv(Request $request): StreamedResponse
    {
        $filename = 'whatsapp-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['occurred_at', 'index_number', 'source_app', 'sender_hint', 'body_preview', 'device_id', 'client_record_id']);

            $this->filteredWhatsappQuery($request)
                ->orderByDesc('occurred_at')
                ->chunkById(400, function (Collection $rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            optional($row->occurred_at)->toIso8601String(),
                            $row->index_number,
                            $row->source_app,
                            $row->sender_hint,
                            $row->body_preview,
                            $row->device_id,
                            $row->client_record_id,
                        ]);
                    }
                });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadWhatsappZip(Request $request)
    {
        if (! class_exists(ZipArchive::class)) {
            return redirect()->back()->with('error', 'ZIP extension is not available on this server.');
        }

        $rows = $this->filteredWhatsappQuery($request)
            ->orderByDesc('occurred_at')
            ->limit(20000)
            ->get();

        $csv = $this->renderWhatsappCsv($rows);
        $zipPath = storage_path('app/tmp/whatsapp-logs-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.zip');
        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0775, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()->back()->with('error', 'Could not build ZIP archive.');
        }

        $zip->addFromString('whatsapp-logs.csv', $csv);
        $zip->addFromString('README.txt', "Export generated at ".now()->toDateTimeString()."\nRows included: ".$rows->count()."\n");
        $zip->close();

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }

    public function purgeWhatsapp(Request $request)
    {
        $deleted = $this->filteredWhatsappQuery($request)->delete();

        return redirect()
            ->route('dashboard.communication-logs.whatsapp.index')
            ->with('success', "Deleted {$deleted} WhatsApp log records.");
    }

    public function deleteSingleWhatsapp(LoggedWhatsappMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()
            ->route('dashboard.communication-logs.whatsapp.index')
            ->with('success', 'Deleted 1 WhatsApp log record.');
    }

    public function whatsappBulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,export_csv,export_zip',
            'selected_ids' => 'required|array|min:1',
            'selected_ids.*' => 'integer|min:1',
        ]);

        $selectedIds = collect($validated['selected_ids'])
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();
        if ($selectedIds === []) {
            return redirect()
                ->route('dashboard.communication-logs.whatsapp.index')
                ->with('error', 'Select at least one message.');
        }

        $request->merge(['selected_ids' => $selectedIds]);

        return match ($validated['action']) {
            'delete' => $this->purgeWhatsapp($request),
            'export_zip' => $this->downloadWhatsappZip($request),
            default => $this->exportWhatsappCsv($request),
        };
    }

    private function applySmsFilters(Builder $query, Request $request): void
    {
        if ($request->filled('index_number')) {
            $query->where('index_number', 'like', '%'.strtoupper(trim((string) $request->index_number)).'%');
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('delivery_status')) {
            $query->where('delivery_status', $request->delivery_status);
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', 'like', '%'.trim((string) $request->device_id).'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('peer_number', 'like', $needle)
                    ->orWhere('body_preview', 'like', $needle)
                    ->orWhere('device_id', 'like', $needle)
                    ->orWhere('client_record_id', 'like', $needle);
            });
        }
    }

    private function applyCallFilters(Builder $query, Request $request): void
    {
        if ($request->filled('index_number')) {
            $query->where('index_number', 'like', '%'.strtoupper(trim((string) $request->index_number)).'%');
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('call_outcome')) {
            $query->where('call_outcome', $request->call_outcome);
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', 'like', '%'.trim((string) $request->device_id).'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('peer_number', 'like', $needle)
                    ->orWhere('device_id', 'like', $needle)
                    ->orWhere('client_record_id', 'like', $needle);
            });
        }
    }

    private function applyWhatsappFilters(Builder $query, Request $request): void
    {
        if ($request->filled('index_number')) {
            $query->where('index_number', 'like', '%'.strtoupper(trim((string) $request->index_number)).'%');
        }
        if ($request->filled('source_app')) {
            $query->where('source_app', $request->source_app);
        }
        if ($request->filled('device_id')) {
            $query->where('device_id', 'like', '%'.trim((string) $request->device_id).'%');
        }
        if ($request->filled('from')) {
            $query->whereDate('occurred_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('occurred_at', '<=', $request->to);
        }
        if ($request->filled('q')) {
            $needle = '%'.trim((string) $request->q).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('sender_hint', 'like', $needle)
                    ->orWhere('body_preview', 'like', $needle)
                    ->orWhere('device_id', 'like', $needle)
                    ->orWhere('client_record_id', 'like', $needle);
            });
        }
    }

    private function filteredWhatsappQuery(Request $request): EloquentBuilder
    {
        $query = LoggedWhatsappMessage::query();
        $this->applyWhatsappFilters($query, $request);
        $selectedIds = $this->selectedWhatsappIds($request);
        if ($selectedIds !== []) {
            $query->whereIn('id', $selectedIds);
        } elseif ($request->filled('id')) {
            $query->where('id', (int) $request->query('id'));
        }

        return $query;
    }

    private function filteredSmsQuery(Request $request): EloquentBuilder
    {
        $query = LoggedSms::query();
        $this->applySmsFilters($query, $request);
        $selectedIds = $this->selectedIds($request->input('selected_ids'));
        if ($selectedIds !== []) {
            $query->whereIn('id', $selectedIds);
        } elseif ($request->filled('id')) {
            $query->where('id', (int) $request->query('id'));
        }

        return $query;
    }

    private function filteredCallQuery(Request $request): EloquentBuilder
    {
        $query = CallLog::query();
        $this->applyCallFilters($query, $request);
        $selectedIds = $this->selectedIds($request->input('selected_ids'));
        if ($selectedIds !== []) {
            $query->whereIn('id', $selectedIds);
        } elseif ($request->filled('id')) {
            $query->where('id', (int) $request->query('id'));
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function selectedWhatsappIds(Request $request): array
    {
        $raw = $request->input('selected_ids');
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $raw
     * @return list<int>
     */
    private function selectedIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, LoggedWhatsappMessage>  $rows
     */
    private function renderWhatsappCsv(Collection $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['occurred_at', 'index_number', 'source_app', 'sender_hint', 'body_preview', 'device_id', 'client_record_id']);
        foreach ($rows as $row) {
            fputcsv($stream, [
                optional($row->occurred_at)->toIso8601String(),
                $row->index_number,
                $row->source_app,
                $row->sender_hint,
                $row->body_preview,
                $row->device_id,
                $row->client_record_id,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    /**
     * @param  Collection<int, LoggedSms>  $rows
     */
    private function renderSmsCsv(Collection $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, ['occurred_at', 'index_number', 'direction', 'delivery_status', 'peer_number', 'body_preview', 'device_id', 'client_record_id']);
        foreach ($rows as $row) {
            fputcsv($stream, [
                optional($row->occurred_at)->toIso8601String(),
                $row->index_number,
                $row->direction,
                $row->delivery_status,
                $row->peer_number,
                $row->body_preview,
                $row->device_id,
                $row->client_record_id,
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    /**
     * @param  Collection<int, CallLog>  $rows
     */
    private function renderCallCsv(Collection $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            return '';
        }
        fputcsv($stream, ['occurred_at', 'index_number', 'direction', 'call_outcome', 'duration_seconds', 'peer_number', 'device_id', 'client_record_id']);
        foreach ($rows as $row) {
            fputcsv($stream, [
                optional($row->occurred_at)->toIso8601String(),
                $row->index_number,
                $row->direction,
                $row->call_outcome,
                $row->duration_seconds,
                $row->peer_number,
                $row->device_id,
                $row->client_record_id,
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }
}
