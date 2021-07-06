<html>
<head>
    <title>Ошибка кода</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,500,600&.css?v58" rel="stylesheet">
    <style>
        <?=$style?>
    </style>
</head>


<body class="horizontal-layout horizontal-menu 2-columns  navbar-floating footer-static  " data-open="click"
      data-menu="horizontal-menu" data-col="2-columns">


<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1><?=$error?></h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3>Data</h3>
                </div>
                <div class="card-body">
                    <pre><?=print_r($data, 1);?></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="row match-height">
        <?
        foreach ($arrays as $key => $arr): ?>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h3><?=ucfirst($key)?></h3>
                    </div>
                    <div class="card-body">
                        <pre><?=print_r($arr, 1);?></pre>
                    </div>
                </div>
            </div>
        <?
        endforeach; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Backtrace</h3>
        </div>
        <div class="card-body">
            <pre><?=print_r($trace, 1);?></pre>
        </div>
    </div>


</div>

<script type="text/javascript">
    <?=$script?>
</script>

</body>
</html>
