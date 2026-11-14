<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="'البريد الإلكتروني'" class="text-right font-bold text-gray-700" />
            <x-text-input id="email" class="block mt-1 w-full text-right" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-right" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="'كلمة المرور'" class="text-right font-bold text-gray-700" />

            <x-text-input id="password" class="block mt-1 w-full text-right"
                            type="password"
                            name="password"
                            required autocomplete="current-password"
                            placeholder="••••••••" />

            <x-input-error :messages="$errors->get('password')" class="mt-2 text-right" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between mt-4">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" name="remember">
                <span class="mr-2 text-sm text-gray-600 font-medium">تذكرني</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-xs text-blue-600 hover:text-blue-800 underline rounded-md focus:outline-none" href="{{ route('password.request') }}">
                    نسيت كلمة المرور؟
                </a>
            @endif
        </div>

        <div class="mt-6">
            <button type="submit" class="w-full justify-center inline-flex items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-bold text-sm text-white tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md">
                <i class="la la-sign-in ml-1 text-base"></i> تسجيل الدخول
            </button>
        </div>
    </form>
</x-guest-layout>
