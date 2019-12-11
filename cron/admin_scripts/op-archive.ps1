#----- get current date ----#
$Now = Get-Date
$Days = "1"
$TargetFolder = "D:\HostingSpaces\admin\bioappeng.us\data\WS\processed\*.*"
#----- define LastWriteTime parameter based on $Days ---#
$LastWrite = $Now.AddDays(-$Days)
$outfile = "D:\HostingSpaces\admin\bioappeng.us\data\WS\processed\archive\OP_2018.all.txt"
 
#----- get files based on lastwrite filter and specified folder ---#
$Files = Get-Childitem $TargetFolder -Include Oaklawn*_WS.dat | Where {$_.LastWriteTime -le "$LastWrite"}
foreach ($File in $Files) 
    {
    write-host "Archiving File $File" -ForegroundColor "DarkRed"
    if ($File -ne $NULL)
        {
        write-host "Archiving File $File" -ForegroundColor "DarkRed"
        $File.Name | Add-Content $outfile
        Get-Content $File.FullName | Add-Content $outfile
        Remove-Item $File.FullName | out-null
        }
    else
        {
        Write-Host "No more files to archive!" -foregroundcolor "Green"
        }
    }

