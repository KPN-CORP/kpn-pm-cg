<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>KPI</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Weightage') }}</th>
                <th>Target</th>
                <th>{{ __('Actual') }}</th>
                <th>{{ __('Achievement') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($form as $key => $data)
                @if (is_array($data))
                    <tr>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0">{{ $key + 1 }}</p>
                        </td>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0 text-muted" style="white-space: pre-line">{{ $data['kpi'] ?? 'N/A' }}</p>
                        </td>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0 text-muted">{{ $data['type'] ?? 'N/A' }}</p>
                        </td>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0 text-muted">{{ $data['weightage'] ?? 'N/A' }}%</p>
                        </td>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0 text-muted">
                                {{ $data['target'] ?? '-' }} 
                                {{ is_null($data['custom_uom'] ?? null) ? ($data['uom'] ?? 'N/A') : $data['custom_uom'] }}
                            </p>
                        </td>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0 text-muted">
                                {{ $data['achievement'] ?? '-' }}
                                {{ is_null($data['custom_uom'] ?? null) ? ($data['uom'] ?? 'N/A') : $data['custom_uom'] }}
                            </p>
                        </td>
                        <td class="{{ $loop->last ? 'border-0' : 'border-bottom-2 border-dashed' }}">
                            <p class="mt-1 mb-0 text-muted">{{ isset($data['percentage']) ? round($data['percentage']) . '%' : '0%' }}</p>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>