@php
    $idx = strtoupper($student->index_number ?? '');
    $programClass = str_contains($idx, 'ITN') ? 'bg-blue-100 text-blue-700' : (str_contains($idx, 'ITS') ? 'bg-emerald-100 text-emerald-700' : (str_contains($idx, 'ITD') ? 'bg-violet-100 text-violet-700' : 'bg-gray-100 text-gray-600'));
    $showRepColumn = $showRepColumn ?? true;
    $compact = $compact ?? false;
    $tc = $compact ? 'px-2 py-1.5 text-xs' : 'px-4 py-3 text-sm';
    $tcName = $compact ? 'px-2 py-1.5 text-xs' : 'px-4 py-3 text-sm';
    $tcMono = $compact ? 'px-2 py-1.5 font-mono text-xs' : 'px-4 py-3 font-mono text-sm';
    $progPad = $compact ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-0.5 text-xs';
    $btn = $compact ? 'w-7 h-7 rounded-md' : 'w-9 h-9 rounded-lg';
    $imgSize = $compact ? 'h-7 w-7' : 'h-9 w-9';
    $imgText = $compact ? 'text-[10px]' : 'text-xs';
@endphp
<tr class="hover:bg-gray-50/50 even:bg-gray-50/40">
    <td class="{{ $tc }} text-gray-500 tabular-nums align-top w-10">{{ $serial }}</td>
    <td class="{{ $tc }} align-top w-12">
        @if($student->profile_image)
        <img src="{{ $student->profileImageUrl() }}" alt="" class="{{ $imgSize }} rounded-full object-cover border border-gray-200 bg-gray-50" loading="lazy">
        @else
        <span class="inline-flex {{ $imgSize }} rounded-full bg-primary/10 text-primary items-center justify-center font-semibold {{ $imgText }}">{{ $student->avatarInitials() }}</span>
        @endif
    </td>
    <td class="{{ $tcMono }} text-gray-800 whitespace-nowrap align-top">{{ $student->index_number }}</td>
    <td class="{{ $tcName }} min-w-0 align-top text-gray-900">
        @if($student->getDisplayName() !== '')
            <span class="font-medium">{{ $student->getDisplayName() }}</span>
        @endif
    </td>
    <td class="{{ $tc }} hidden md:table-cell align-top">
        <span class="inline-block {{ $progPad }} rounded font-medium {{ $programClass }}">{{ $student->getProgramLabel() }}</span>
    </td>
    @if($showRepColumn)
    <td class="{{ $tc }} align-top">
        @if($student->isRep())
        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-800">Rep</span>
        @else
        <span class="text-gray-400 {{ $compact ? 'text-xs' : 'text-sm' }}">—</span>
        @endif
    </td>
    @endif
    <td class="{{ $tc }} text-right align-top w-12">
        <a href="{{ $detailUrl }}" class="inline-flex items-center justify-center {{ $btn }} border border-gray-200 text-gray-600 hover:bg-primary/10 hover:border-primary/30 hover:text-primary transition-colors" title="View">
            <i class="fas fa-chevron-right {{ $compact ? 'text-[10px]' : 'text-sm' }}"></i>
        </a>
    </td>
</tr>
