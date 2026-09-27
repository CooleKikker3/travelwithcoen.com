<x-layouts.app :title="__('site.login.title')">
    <x-page-header :title="__('site.login.title')" :lead="__('site.login.lead')" />

    <div class="container-page mt-10 max-w-md">
        <form method="POST" action="{{ lroute('login') }}" class="space-y-5 rounded-2xl bg-white p-6 ring-1 ring-sage-200">
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
            <button class="w-full rounded-full bg-forest-800 px-5 py-2.5 font-semibold text-white hover:bg-forest-700">{{ __('site.login.submit') }}</button>
        </form>
    </div>
</x-layouts.app>
