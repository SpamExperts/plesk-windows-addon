@echo off

: Script uses WSF and VBS to download and unzip package
: TODO checking administrator privileges
: TODO specify PHP.exe location if it isn't in Plesk directory

echo Downloading latest package of Professional Spam Filter.
cscript //nologo "%~f0?.wsf" %1 //job:VBS
IF %ERRORLEVEL% EQU 1 goto Abort
if NOT exist "%tmp%\prospamfilter\bin\install.php" (
    echo *** CANNOT LOCATE install.php file. Installation ABORTED ***
    exit /b 
)
echo Installation will start now.
"%plesk_bin%\php.exe" -d safe_mode=0 "%tmp%\prospamfilter\bin\install.php"
PAUSE
exit /b

:Abort
echo Installation process aborted.
PAUSE
exit /b

<package>
  <job id="VBS">
    <script language="VBScript">

        Dim bRestrictedToFrozen
        bRestrictedToFrozen = false

        Function checkPHPversion()

            sRequiredVersion = "5.2.4"
            sRecommendedVersion = "5.4"

            Set FSO = CreateObject("Scripting.FileSystemObject")
            If NOT FSO.FileExists(sPHPDir & "php.exe") Then
                WScript.echo("PHP file not found")
                WScript.quit
            End If

            ' TO DO Specify PHP location

            sCommand = chr(34) & sPHPDir & "php.exe" & chr(34) & " -v " & chr(34)
            Set WshShell = CreateObject("WScript.shell")
            Set WshShellExec = WshShell.Exec(sCommand)

            Select Case WshShellExec.Status
               Case WshFinished
                   strOutput = WshShellExec.StdOut.ReadAll
               Case WshFailed
                   strOutput = WshShellExec.StdErr.ReadAll
            End Select

            x = Split(strOutput)
            sInstalledVersion = x(1)
            Dim Inst
            Inst = Split(sInstalledVersion, ".")
            Dim Rec
            Rec = Split(sRecommendedVersion, ".")
            Length = Ubound(Rec)
            For i=0 To Length
                If Rec(i) > Inst(i) Then
                    WScript.echo("You are running an old PHP version no longer actively supported by this addon so only the 'frozen' update tier is available, for critical bugfixes. It is highly recommended to upgrade to a newer PHP version (>=" & sRecommendedVersion & ") to have access to all the latest features.")
                    bRestrictedToFrozen = true
                    Exit For
                ElseIf Inst(i) > Rec(i) Then
                    Exit Function
                End If
            Next

            Dim Req
            Req = Split(sRequiredVersion, ".")
            Length = Ubound(Req)
            For i=0 To Length
                If Req(i) > Inst(i) Then
                    WScript.echo("PHP Version: " & sRequiredVersion & " or newer is required!")
                    WScript.quit(1)
                ElseIf Inst(i) > Req(i) Then
                    Exit Function
                End If
            Next    
        End Function

        With CreateObject("WScript.Shell")
            sTempZipFile = .Environment("Process").Item("TMP") & "\spamexpertstemporary.zip"
            sTempDir = .Environment("Process").Item("TMP") & "\prospamfilter"
            sPHPDir = .Environment("Process").Item("plesk_dir") & "admin\bin\"
        End With
        
        set oArgs = WScript.Arguments
        checkPHPVersion()

        sBaseUrl="http://download.seinternal.com/integration"
        sDownloadBaseUrl = sBaseUrl & "/files/plesk"

        if bRestrictedToFrozen Then
            sCHECKURL = sBaseUrl & "/?act=getversion&panel=plesk&tier=frozen&pkgtype=zip"
            sfilepart = "_frozen.zip"
        else
            if NOT oArgs.count = 0 Then
                if oArgs(0) = "frozen" Then
                    sCHECKURL = sBaseUrl & "/?act=getversion&panel=plesk&tier=frozen&pkgtype=zip"
                    sfilepart = "_frozen.zip"
                elseif oArgs(0) = "trunk" Or oArgs(0) = "master" Then
                    sCHECKURL = sBaseUrl & "/?act=getversion&panel=plesk&tier=testing&pkgtype=zip"
                    sfilepart = "_testing.zip"
                else
                    sCHECKURL = sBaseUrl & "/?act=getversion&panel=plesk&tier=testing&pkgtype=zip&branch=" & oArgs(0)
                    sfilepart = "_testing_" & oArgs(0) & ".zip"
                End If
            else
                sCHECKURL= sBaseUrl & "/?act=getversion&panel=plesk&tier=stable&pkgtype=zip"
                sfilepart = "_stable.zip"
            End If
        End if

        Set check = CreateObject("MSXML2.XMLHTTP")
        check.Open "GET", sCHECKURL, false
        check.Send()
        Set reg = new RegExp
        reg.Pattern = "[0-9]*\.[0-9]*\.[0-9]*"
        Set matches = reg.Execute(check.responseText)
        If matches.count = 0 Then 
            WScript.echo "Cannot Check Current Version! ABORTED"
            WScript.quit(1)
        End If
        sVersion = "v" & matches(0)
        sFullFile = sVersion & sfilepart
        sDownloadUrl = sDownloadBaseUrl & "/" & sFullFile

        With CreateObject("MSXML2.XMLHTTP")
            .Open "GET", sDownloadUrl, false
            .Send()
            If .Status = 200 Then
                Response = .ResponseBody
                With CreateObject("Scripting.FileSystemObject")
                        If .FolderExists(sTempDir) Then
                                 .DeleteFolder(sTempDir)
                        End If
                        If NOT .FolderExists(sTempDir) Then
                                 .CreateFolder(sTempDir)
                        End If
                        If .FileExists(sTempZipFile) Then
                                 .DeleteFile sTempZipFile
                        End If
                End With
                With CreateObject("ADODB.Stream")
                    .Open
                    .Type = 1
                    .Write Response
                    .Position = 0
                    .SaveToFile sTempZipFile, 1
                End With
            Else
                WScript.echo("Unable to download installation package.")
                WScript.quit(1)                   
            End If
        End With

        set objShell = CreateObject("Shell.Application")
        set objSource = objShell.NameSpace( sTempZipFile ).Items()
        set objDest = objShell.NameSpace( sTempDir )
        objDest.CopyHere(objSource)
        With CreateObject("Scripting.FileSystemObject")
                 .DeleteFile sTempZipFile
        End With
    </script>
  </job>
</package>