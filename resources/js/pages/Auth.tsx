import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import {
    login as submitLogin,
    register as submitRegistration,
} from '@/actions/App/Http/Controllers/AuthController';
import { login, register } from '@/routes';

export default function Auth({ registering }: { registering: boolean }) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const title = registering ? 'Create an account' : 'Log in';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(registering ? submitRegistration.url() : submitLogin.url(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <main className="flex min-h-screen items-center justify-center bg-[#000018] p-6 text-white">
            <Head title={title} />
            <form
                onSubmit={submit}
                className="flex w-full max-w-md flex-col gap-4 rounded border border-[#3355dd] bg-[#101358] p-6"
            >
                <h1 className="text-2xl font-bold text-[#f5f000]">{title}</h1>
                {registering && (
                    <label className="flex flex-col gap-2">
                        Name
                        <input
                            name="name"
                            autoComplete="name"
                            required
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            className="rounded border border-[#3355dd] bg-black/30 p-2"
                        />
                        {form.errors.name && (
                            <span role="alert" className="text-red-300">
                                {form.errors.name}
                            </span>
                        )}
                    </label>
                )}
                <label className="flex flex-col gap-2">
                    Email
                    <input
                        name="email"
                        type="email"
                        autoComplete="username"
                        required
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        className="rounded border border-[#3355dd] bg-black/30 p-2"
                    />
                    {form.errors.email && (
                        <span role="alert" className="text-red-300">
                            {form.errors.email}
                        </span>
                    )}
                </label>
                <label className="flex flex-col gap-2">
                    Password
                    <input
                        name="password"
                        type="password"
                        autoComplete={
                            registering ? 'new-password' : 'current-password'
                        }
                        required
                        value={form.data.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                        className="rounded border border-[#3355dd] bg-black/30 p-2"
                    />
                    {form.errors.password && (
                        <span role="alert" className="text-red-300">
                            {form.errors.password}
                        </span>
                    )}
                </label>
                {registering && (
                    <label className="flex flex-col gap-2">
                        Confirm password
                        <input
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            className="rounded border border-[#3355dd] bg-black/30 p-2"
                        />
                    </label>
                )}
                <button
                    type="submit"
                    disabled={form.processing}
                    className="rounded border border-[#3355dd] bg-black/30 px-4 py-3 font-semibold text-[#f5f000] hover:bg-white/10 disabled:opacity-50"
                >
                    {form.processing ? 'Please wait…' : title}
                </button>
                <Link
                    href={registering ? login.url() : register.url()}
                    className="text-center text-cyan-300 underline"
                >
                    {registering
                        ? 'Already have an account? Log in'
                        : 'Create an account'}
                </Link>
            </form>
        </main>
    );
}
