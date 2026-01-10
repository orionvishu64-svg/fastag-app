<?php
// admin_editor.php
// simple demo – no auth for now
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Editor</title>

    <!-- TinyMCE local file -->
    <script src="/tinymce/js/tinymce/tinymce.min.js"></script>

    <script>
    tinymce.init({
        selector: '#content',
        height: 500,

        plugins: `
            accordion
            lists
            link
            image
            table
            code
            preview
        `,

        toolbar: `
            undo redo |
            formatselect |
            bold italic underline |
            bullist numlist |
            link image table |
            accordion |
            code preview
        `,

        menubar: true,
        branding: false
    });
    </script>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f5f5f5;
        }
        .container {
            background: #fff;
            padding: 20px;
            border-radius: 6px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Admin Content Editor</h2>

    <form method="post" action="">
        <textarea id="content" name="content">
<h2>Welcome</h2>
<p>Type here like Word.</p>
        </textarea>

        <br><br>
        <button type="submit">Save</button>
    </form>

    <?php
    if (!empty($_POST['content'])) {
        echo "<hr>";
        echo "<h3>Saved HTML Output (preview)</h3>";
        echo "<div style='border:1px solid #ccc;padding:15px'>";
        echo $_POST['content'];
        echo "</div>";
    }
    ?>
</div>

</body>
</html>
