<!-- meta tags and other links -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afrolia Admin - Authentification</title>
    <link rel="icon" type="image/png" href="{{ URL::asset('') }}assets/images/favicon.ico" sizes="16x16">
    <!-- remix icon font css  -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/remixicon.css">
    <!-- BootStrap css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/bootstrap.min.css">
    <!-- Apex Chart css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/apexcharts.css">
    <!-- Data Table css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/dataTables.min.css">
    <!-- Text Editor css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/editor-katex.min.css">
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/editor.atom-one-dark.min.css">
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/editor.quill.snow.css">
    <!-- Date picker css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/flatpickr.min.css">
    <!-- Calendar css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/full-calendar.css">
    <!-- Vector Map css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/jquery-jvectormap-2.0.5.css">
    <!-- Popup css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/magnific-popup.css">
    <!-- Slick Slider css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/lib/slick.css">
    <!-- main css -->
    <link rel="stylesheet" href="{{ URL::asset('') }}assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
</head>

<body>

    <section class="auth bg-base d-flex flex-wrap">
        <div class="auth-left d-lg-block d-none">
            <div class="d-flex align-items-center flex-column h-100 justify-content-center">
                <img src="{{ URL::asset('') }}assets/images/auth/auth-img.png" alt="">
            </div>
        </div>
        <div class="auth-right py-32 px-24 d-flex flex-column justify-content-center">
            <div class="max-w-464-px mx-auto w-100">
                <div>
                    <h3 class="mb-40 max-w-290-px"
                        style="color: #92400E; font-family: 'Pacifico', cursive; font-size: 32px;">
                        Afrolia
                    </h3>
                    <h4 class="mb-12">Inscrivez-vous</h4>
                    <p class="mb-32 text-secondary-light text-lg">Bienvenue ! Veuillez saisir vos coordonnées</p>
                </div>
                <form action="#">
                    @csrf
                    <div class="icon-field mb-16">
                        <span class="icon top-50 translate-middle-y">
                            <iconify-icon icon="f7:person"></iconify-icon>
                        </span>
                        <input name="username" required type="text"
                            class="form-control h-56-px bg-neutral-50 radius-12" placeholder="Nom d'utilisateur">
                    </div>
                    <div class="icon-field mb-16">
                        <span class="icon top-50 translate-middle-y">
                            <iconify-icon icon="mage:email"></iconify-icon>
                        </span>
                        <input type="email" required name="email"
                            class="form-control h-56-px bg-neutral-50 radius-12" placeholder="E-mail">
                    </div>
                    <div class="mb-20">
                        <div class="position-relative ">
                            <div class="icon-field">
                                <span class="icon top-50 translate-middle-y">
                                    <iconify-icon icon="solar:lock-password-outline"></iconify-icon>
                                </span>
                                <input required name="password" type="password"
                                    class="form-control h-56-px bg-neutral-50 radius-12" id="your-password"
                                    placeholder="Mot de passe">
                            </div>
                            <span
                                class="toggle-password ri-eye-line cursor-pointer position-absolute end-0 top-50 translate-middle-y me-16 text-secondary-light"
                                data-toggle="#your-password"></span>
                        </div>
                        <span class="mt-12 text-sm text-secondary-light text-danger">Votre mot de passe doit comporter
                            au moins 8
                            caractères</span>
                    </div>
                    <div class="">
                        <div class="d-flex justify-content-between gap-2">
                            <div class="form-check style-check d-flex align-items-start">
                                <input class="form-check-input border border-neutral-300 mt-4" type="checkbox"
                                    value="" id="condition">
                                <label class="form-check-label text-sm" for="condition">
                                    En créant un compte, vous acceptez les
                                    <a href="javascript:void(0)" target="_blank"
                                        class="text-primary-600 fw-semibold">Termes &
                                        Conditions</a> et notre
                                    <a href="javascript:void(0)" target="_blank"
                                        class="text-primary-600 fw-semibold">Politique de confidentialité</a>
                                </label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary text-sm btn-sm px-12 py-16 w-100 radius-12 mt-32">
                        S'enregistrer</button>

                    <div class="mt-32 center-border-horizontal text-center">
                        <span class="bg-base z-1 px-4">Ou</span>
                    </div>
                    <div class="mt-32 text-center text-sm">
                        <p class="mb-0">Vous avez déjà un compte? <a href="{{ url('admin') }}"
                                class="text-primary-600 fw-semibold">Se connecter</a></p>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- jQuery library js -->
    <script src="{{ URL::asset('') }}assets/js/lib/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap js -->
    <script src="{{ URL::asset('') }}assets/js/lib/bootstrap.bundle.min.js"></script>
    <!-- Apex Chart js -->
    <script src="{{ URL::asset('') }}assets/js/lib/apexcharts.min.js"></script>
    <!-- Data Table js -->
    <script src="{{ URL::asset('') }}assets/js/lib/dataTables.min.js"></script>
    <!-- Iconify Font js -->
    <script src="{{ URL::asset('') }}assets/js/lib/iconify-icon.min.js"></script>
    <!-- jQuery UI js -->
    <script src="{{ URL::asset('') }}assets/js/lib/jquery-ui.min.js"></script>
    <!-- Vector Map js -->
    <script src="{{ URL::asset('') }}assets/js/lib/jquery-jvectormap-2.0.5.min.js"></script>
    <script src="{{ URL::asset('') }}assets/js/lib/jquery-jvectormap-world-mill-en.js"></script>
    <!-- Popup js -->
    <script src="{{ URL::asset('') }}assets/js/lib/magnifc-popup.min.js"></script>
    <!-- Slick Slider js -->
    <script src="{{ URL::asset('') }}assets/js/lib/slick.min.js"></script>
    <!-- main js -->
    <script src="{{ URL::asset('') }}assets/js/app.js"></script>

    <script>
        function initializePasswordToggle(toggleSelector) {
            $(toggleSelector).on('click', function() {
                $(this).toggleClass("ri-eye-off-line");
                var input = $($(this).attr("data-toggle"));
                if (input.attr("type") === "password") {
                    input.attr("type", "text");
                } else {
                    input.attr("type", "password");
                }
            });
        }
        // Call the function
        initializePasswordToggle('.toggle-password');
    </script>

</body>

</html>
