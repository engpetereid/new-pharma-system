<!DOCTYPE html>
<html class="loading" lang="en" data-textdirection="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta name="description" content="RUQAX Pharmaceuticals — Innovation for better health">
    
    <meta name="author" content="RUQAX Pharmaceuticals">
    <title>@yield('title') | RUQAX</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{asset('assets/admin/images/brand/favicon-32.png')}}">
    <link rel="shortcut icon" href="{{asset('assets/admin/images/brand/favicon.ico')}}">
    <link rel="apple-touch-icon" href="{{asset('assets/admin/images/brand/favicon-180.png')}}">
    <meta name="theme-color" content="#323E4D">

    <link
        href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Quicksand:300,400,500,700"
        rel="stylesheet">


    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/line-awesome.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/plugins/animate/animate.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/vendors.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/all.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/weather-icons/climacons.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/fonts/meteocons/style.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/charts/morris.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/charts/chartist.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/forms/selects/select2.min.css')}}">
    <link rel="stylesheet" type="text/css"
          href="{{asset('assets/admin/vendors/css/charts/chartist-plugin-tooltip.css')}}">
    <link rel="stylesheet" type="text/css"
          href="{{asset('assets/admin/vendors/css/forms/toggle/bootstrap-switch.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/forms/toggle/switchery.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/core/menu/menu-types/vertical-menu.css')}}">


    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/app.css')}}">

    <link rel="stylesheet" type="text/css"
          href="{{asset('assets/admin/css/core/menu/menu-types/vertical-menu.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/core/colors/palette-gradient.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/fonts/simple-line-icons/style.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/core/colors/palette-gradient.css')}}">


    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/cryptocoins/cryptocoins.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/extensions/datedropper.min.css')}}">
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/vendors/css/extensions/timedropper.min.css')}}">

    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/style.css')}}">

    @yield('style')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body, h1, h2, h3, h4, h5, h6, .navigation-header, .brand-text, .main-menu, .navbar, table, input, select, textarea, button {
            font-family: 'Cairo', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
        }

        body {
            font-size: 14.5px;
            line-height: 1.65;
            color: #1e293b;
            font-weight: 500;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #0f172a;
            font-weight: 700 !important;
            letter-spacing: -0.01em;
        }

        /* High clarity for tables */
        table {
            color: #1e293b;
        }

        .table thead th {
            font-size: 13.5px;
            font-weight: 700 !important;
            color: #0f172a !important;
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            vertical-align: middle;
        }

        .table tbody td {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
            vertical-align: middle;
            border-top: 1px solid #f1f5f9;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(248, 250, 252, 0.7);
        }

        .table-hover tbody tr:hover {
            background-color: #f1f5f9;
        }

        /* High clarity for form inputs */
        input.form-control,
        textarea.form-control,
        select.form-control {
            direction: rtl;
            text-align: right;
            font-size: 14px !important;
            font-weight: 500 !important;
            color: #0f172a !important;
            border-color: #cbd5e1;
            line-height: 1.5;
        }

        .form-control:focus {
            color: #0f172a !important;
            border-color: #1E9FF2;
            box-shadow: 0 0 0 0.2rem rgba(30, 159, 242, 0.15);
        }

        .form-control::placeholder {
            color: #94a3b8 !important;
            font-weight: 400;
        }

        label {
            font-size: 13.5px;
            font-weight: 600 !important;
            color: #1e293b;
            margin-bottom: 0.35rem;
        }

        /* Navigation & Sidebar clarity */
        .main-menu .navigation li a {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: #334155 !important;
        }

        .main-menu .navigation .navigation-header span {
            font-size: 12px !important;
            font-weight: 800 !important;
            color: #64748b !important;
            letter-spacing: 0.5px;
        }

        /* Text muted & small contrast improvement */
        .text-muted, small {
            color: #475569 !important;
            font-weight: 500;
        }

        /* Badges */
        .badge {
            font-size: 12px !important;
            font-weight: 700 !important;
            padding: 0.4em 0.7em !important;
            letter-spacing: 0;
        }

        .card .card-title {
            font-size: 1.15rem;
            font-weight: 700 !important;
            color: #0f172a;
        }

        .card .card-subtitle {
            color: #475569;
            font-size: 0.9rem;
        }

        .has-icon-left .form-control {
            padding-right: 3rem;
            padding-left: 0.75rem;
        }

        .has-icon-left .form-control-position {
            right: 0;
            left: auto;
        }

        .pagination {
            justify-content: center;
            margin-top: 20px;
        }

        .page-item.active .page-link {
            background-color: #1E9FF2 !important;
            border-color: #1E9FF2 !important;
            color: #fff !important;
            box-shadow: 0 2px 4px 0 rgba(30, 159, 242, 0.5);
        }

        .page-link {
            color: #1E9FF2;
            border: 1px solid #E3EBF3;
            margin: 0 2px;
            border-radius: 5px;
        }

        .page-link:hover {
            color: #00BFA5;
            background-color: #f0f2f5;
            border-color: #1E9FF2;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #E3EBF3;
        }

        @media print {
            @page { size: A4 ; margin: 5px !important; }
            .sidebar,
            .main-header,
            .navbar,
            .btn,
            .no-print {
                display: none !important;
            }

            .content-wrapper,
            .main-panel,
            body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .table-responsive {
                overflow: visible !important;
                display: block !important;
                width: 100% !important;
            }

            @page {
                /*size: landscape;*/
                margin: 10mm;
            }

            .vertical-layout.vertical-menu.menu-expanded .content,
            .vertical-layout.vertical-menu.menu-expanded .content-wrapper {
                padding-left: 0 !important;
                margin-left: 0 !important;
            }
        }
    </style>
    <link rel="stylesheet" type="text/css" href="{{asset('assets/admin/css/ruqax-theme.css')}}">
</head>
<body class="vertical-layout vertical-menu 2-columns menu-expanded fixed-navbar"
      data-open="click" data-menu="vertical-menu" data-col="2-columns">
@include('viewer.includes.header')
@include('viewer.includes.sidebar')
@yield('content')

@include('viewer.includes.footer')
<script src="{{asset('assets/admin/vendors/js/vendors.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/tables/datatable/datatables.min.js')}}"
        type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/tables/datatable/dataTables.buttons.min.js')}}"
        type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/forms/toggle/bootstrap-switch.min.js')}}"
        type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/forms/toggle/bootstrap-checkbox.min.js')}}"
        type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/forms/toggle/switchery.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/js/scripts/forms/switch.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/forms/select/select2.full.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/js/scripts/forms/select/form-select2.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/charts/chart.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/charts/echarts/echarts.js')}}" type="text/javascript"></script>

<script src="{{asset('assets/admin/vendors/js/extensions/datedropper.min.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/vendors/js/extensions/timedropper.min.js')}}" type="text/javascript"></script>

<script src="{{asset('assets/admin/vendors/js/forms/icheck/icheck.min.js')}}" type="text/javascript"></script>


<script src="{{asset('assets/admin/js/core/app-menu.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/js/core/app.js')}}" type="text/javascript"></script>
<script src="{{asset('assets/admin/js/scripts/customizer.js')}}" type="text/javascript"></script>


<script src="{{asset('assets/admin/js/scripts/tables/datatables/datatable-basic.js')}}"
        type="text/javascript"></script>
<script src="{{asset('assets/admin/js/scripts/extensions/date-time-dropper.js')}}" type="text/javascript"></script>

<script src="{{asset('assets/admin/js/scripts/forms/checkbox-radio.js')}}" type="text/javascript"></script>

<script src="{{asset('assets/admin/js/scripts/modal/components-modal.js')}}" type="text/javascript"></script>

<script>
    $('#meridians1').timeDropper({
        meridians: true,
        setCurrentTime: false
    });
    $('#meridians2').timeDropper({
        meridians: true, setCurrentTime: false

    });
    $('#meridians3').timeDropper({
        meridians: true,
        setCurrentTime: false
    });
    $('#meridians4').timeDropper({
        meridians: true,
        setCurrentTime: false
    });
    $('#meridians5').timeDropper({
        meridians: true, setCurrentTime: false

    });
    $('#meridians6').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians7').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians8').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians9').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians10').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians11').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians12').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians13').timeDropper({
        meridians: true, setCurrentTime: false
    });
    $('#meridians14').timeDropper({
        meridians: true, setCurrentTime: false
    });
</script>
@yield('script')
</body>
</html>
