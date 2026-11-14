<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="'الاسم بالكامل'" class="text-right font-bold text-gray-700" />
            <x-text-input id="name" class="block mt-1 w-full text-right" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="مثال: د. أحمد محمد" />
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-right" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="'البريد الإلكتروني'" class="text-right font-bold text-gray-700" />
            <x-text-input id="email" class="block mt-1 w-full text-right" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="name@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-right" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="'كلمة المرور'" class="text-right font-bold text-gray-700" />

            <x-text-input id="password" class="block mt-1 w-full text-right"
                            type="password"
                            name="password"
                            required autocomplete="new-password"
                            placeholder="••••••••" />

            <x-input-error :messages="$errors->get('password')" class="mt-2 text-right" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="'تأكيد كلمة المرور'" class="text-right font-bold text-gray-700" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full text-right"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password"
                            placeholder="••••••••" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-right" />
        </div>

        <div class="flex items-center justify-between mt-6">
            <a class="text-xs text-blue-600 hover:text-blue-800 underline rounded-md focus:outline-none" href="{{ route('login') }}">
                لديك حساب بالفعل؟ تسجيل الدخول
            </a>

            <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-bold text-sm text-white tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 shadow-md">
                إنشاء الحساب
            </button>
        </div>
    </form>
</x-guest-layout>
