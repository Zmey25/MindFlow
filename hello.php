<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>hello there</title>
</head>
<body>
    <h1></h1>

    <?php
        $imagePath = '/assets/images/hello.png';
        $altText = 'no';
    ?>

    <img src="<?php echo $imagePath; ?>" alt="<?php echo $altText; ?>">
</body>
</html>
