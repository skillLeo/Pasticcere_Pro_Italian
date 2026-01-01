<!DOCTYPE html>
<html>
<head>
    <title>OCR Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: monospace; padding: 20px; }
        textarea { width: 100%; height: 400px; font-family: monospace; }
        button { padding: 10px 20px; font-size: 16px; }
    </style>
</head>
<body>
    <h1>Test OCR Extraction</h1>
    
    <form id="testForm">
        <input type="file" name="invoice_file" accept=".jpg,.jpeg,.png" required>
        <button type="submit">Test OCR</button>
    </form>
    
    <h2>Extracted Text:</h2>
    <textarea id="result" readonly></textarea>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            document.getElementById('result').value = 'Processing...';
            
            try {
                const response = await fetch('/test-ocr-direct', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('result').value = 
                        'EXTRACTED TEXT:\n' +
                        '=================\n' +
                        data.extracted_text + '\n\n' +
                        'TOTAL LINES: ' + data.line_count;
                } else {
                    document.getElementById('result').value = 'ERROR: ' + JSON.stringify(data);
                }
            } catch (error) {
                document.getElementById('result').value = 'ERROR: ' + error.message;
            }
        });
    </script>
</body>
</html>