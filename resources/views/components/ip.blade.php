{{--
    Muestra una IP y, si esta en la lista de vigilancia, una insignia.

    Uso: <x-ip :ip="$event->source_ip" />
--}}
@props(['ip'])

@php($level = \App\Models\WatchedIp::levelFor($ip ? (string) $ip : null))

@if ($ip)
    <code>{{ $ip }}</code>
    @if ($level === 'blocked')
        <span class="badge badge-critical ip-flag" title="IP bloqueada (lista de vigilancia)"><i class="bi bi-slash-circle"></i> Bloqueada</span>
    @elseif ($level === 'watch')
        <span class="badge badge-medium ip-flag" title="IP en la lista de vigilancia"><i class="bi bi-eye-fill"></i> Vigilada</span>
    @endif
@else
    <span class="text-tertiary">-</span>
@endif
