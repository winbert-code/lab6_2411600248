@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-4">
                <h1 class="h3 fw-bold mb-1">My Profile</h1>
                <p class="text-muted mb-0">Update your personal details and login password.</p>
            </div>

            <div class="card app-card mb-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 p-4">
                    <div class="d-flex align-items-center gap-3">
                        <span class="stat-icon icon-primary"><i class="bi bi-person-circle fs-4"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <div class="small text-muted">{{ $user->email }}</div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-dark border">{{ ucfirst($user->role) }}</span>
                        <span class="badge bg-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                            {{ ucfirst($user->status) }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card app-card">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('profile.update') }}" novalidate>
                        @csrf
                        @method('PATCH')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                                <input id="name" name="name" type="text"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $user->name) }}" autofocus>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email address <span class="text-danger">*</span></label>
                                <input id="email" name="email" type="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $user->email) }}">
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">
                                    New password <span class="text-muted small">(leave blank to keep current)</span>
                                </label>
                                <input id="password" name="password" type="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       autocomplete="new-password">
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label">Confirm new password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password"
                                       class="form-control" autocomplete="new-password">
                            </div>
                        </div>

                        <p class="small text-muted mt-3 mb-0">
                            Your role and account status are managed by an administrator.
                        </p>

                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save profile
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
