@extends('layouts.master', ['title' => 'Confidentialité'])

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            $('.summernote').summernote();
        });
    </script>
@endpush

@section('content')
    <div class="dashboard-main-body">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-24">
            <h6 class="fw-semibold mb-0">Confidentialité</h6>
            <ul class="d-flex align-items-center gap-2">
                <li class="fw-medium">
                    <a href="{{ url('/') }}" class="d-flex align-items-center gap-1 hover-text-primary">
                        <iconify-icon icon="solar:home-smile-angle-outline" class="icon text-lg"></iconify-icon>
                        Tableau de bord
                    </a>
                </li>
                <li>-</li>
                <li class="fw-medium">Confidentialité (page "Confidentialité" du profil dans l'application)</li>
            </ul>
        </div>

        @include('layouts.statuts')

        <div class="card basic-data-table radius-12 overflow-hidden">
            <form action="{{ route('termes.storeOrSecurity') }}" method="post">
                @csrf
                <textarea required class="summernote" name="notice">
                {{ old('description', $termes->description ?? '') }}
                </textarea>

                <div class="card-footer p-24 bg-base border border-bottom-0 border-end-0 border-start-0">
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        <button type="submit"
                            class="btn btn-primary border border-primary-600 text-md px-28 py-12 radius-8">
                            {{ $termes ? 'Mettre à jour' : 'Enregistrer' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
