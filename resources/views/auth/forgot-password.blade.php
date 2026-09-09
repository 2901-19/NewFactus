<x-guest-layout>
    <div class="intro mb-2">
        ¿Olvidaste tu contraseña? No hay problema. Indícanos tu correo y te enviaremos un enlace para restablecerla.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Enviar enlace de recuperación') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>