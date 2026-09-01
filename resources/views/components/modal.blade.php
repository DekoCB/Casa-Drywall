@props(['id', 'titulo', 'oscuro' => false])

<div class="modal-overlay" id="{{ $id }}">
    <div class="modal-card">
        <div class="modal-header @if($oscuro) modal-header-dark @endif">
            <h3>{{ $titulo }}</h3>
            <button type="button" class="modal-close" data-cerrar="{{ $id }}" aria-label="Cerrar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            {{ $slot }}
        </div>
    </div>
</div>
