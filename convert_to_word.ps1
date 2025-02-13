$word = New-Object -ComObject Word.Application
$word.Visible = $false

$doc = $word.Documents.Add()
$selection = $word.Selection

# Read the markdown content
$markdownContent = Get-Content "project_report.md" -Raw

# Simple markdown to Word conversion
$lines = $markdownContent -split "`n"
foreach ($line in $lines) {
    # Handle headers
    if ($line -match '^#{1,6}\s+(.+)$') {
        $headerLevel = ($line -split ' ')[0].Length
        $text = $line -replace '^#{1,6}\s+', ''
        $selection.Style = "Heading $headerLevel"
        $selection.TypeText($text)
        $selection.TypeParagraph()
        $selection.Style = "Normal"
    }
    # Handle code blocks
    elseif ($line -match '^```') {
        $selection.Font.Name = "Consolas"
        continue
    }
    # Handle normal text
    else {
        $selection.TypeText($line)
        $selection.TypeParagraph()
    }
}

# Save the document
$docPath = (Get-Item "project_report.md").DirectoryName + "\project_report.docx"
$doc.SaveAs([ref]$docPath, [ref]16)
$doc.Close()
$word.Quit()

[System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
