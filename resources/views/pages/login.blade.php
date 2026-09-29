<x-layouts.app :title="__('site.login.title')">
    <x-page-header :title="__('site.login.title')" :lead="__('site.login.lead')" />

    <div class="container-page mt-10 max-w-md">
        <form method="POST" action="{{ lroute('login') }}" class="polaroid relative z-10 -mt-20 space-y-5 rounded-3xl bg-white p-7 shadow-xl ring-1 ring-sage-200" style="--tilt: -1deg">
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold">{{ __('site.login.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="mt-1 w-full rounded-xl border border-sage-200 px-3 py-2 focus:border-moss-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold">{{ __('site.login.password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="mt-1 w-full rounded-xl border border-sage-200 px-3 py-2 focus:border-moss-500">
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded"> {{ __('site.login.remember') }}
            </label>
            <button class="btn-primary w-full justify-center">{{ __('site.login.submit') }}</button>
        </form>
    </div>
</x-layouts.app>
