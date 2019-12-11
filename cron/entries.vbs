Option Explicit
Dim ie, ipf, body, head
Dim objFSO, objFile, outfile
Dim fdate

Set ie = CreateObject("InternetExplorer.Application")

Function timeStamp()
    Dim t 
    t = Now()
    timeStamp = Year(t) & "-" & _
    Right("0" & Month(t),2)  & "-" & _
    Right("0" & Day(t),2)  & "_" & _  
    Right("0" & Hour(t),2) & _
    Right("0" & Minute(t),2) '    '& _    Right("0" & Second(t),2) 
End Function
 

Sub WaitForLoad   
	Do While IE.Busy
		WScript.Sleep 500
	Loop
End Sub
 
ie.Left = 0
ie.Top = 0
ie.Toolbar = 0
ie.StatusBar = 0
ie.Height = 400
ie.Width = 1020
ie.Resizable = 0
ie.Visible = True 
 
ie.Navigate "http://www.equibase.com/static/entry/index.html?SAP=TN"
'ie.Navigate "https://library.umaine.edu/collection-services/recent-acquisitions/"
 
Call WaitForLoad 
 
head = ie.Document.head.outerHTML
body = ie.Document.body.outerHTML
'wscript.echo html
'~ Create a FileSystemObject
Set objFSO=CreateObject("Scripting.FileSystemObject")
'FDate = Replace(FormatDateTime(Now(),2),"/","-")
FDate = timeStamp()
outfile = "entries-" & FDate& ".html"
Set objFile = objFSO.CreateTextFile(outfile,True, True)
objFile.WriteLine(head)
objFile.WriteLine(body)
objFile.close
ie.Quit
Set ie = Nothing
