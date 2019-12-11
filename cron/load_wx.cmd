D:
cd \HostingSpaces\admin\bioappeng.us\cron
php D:\HostingSpaces\admin\bioappeng.us\cron\wx_splitter.php >> D:\HostingSpaces\admin\bioappeng.us\cron\logs\wxptmp.txt
REM php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_us.php >> D:\HostingSpaces\admin\bioappeng.us\cron\logs\load_wx_log.txt
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_FG.php FAI 
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php OAK
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php SAN
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php AQU
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php Del
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php Chu
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php BEL
REM php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php KEE
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_opt.php SAR
php D:\HostingSpaces\admin\bioappeng.us\cron\load_weather_oo.php

REM - 	now running each separately:
REM - 	First these can go in parallel (xx = 5 min interval
REM -		xx+1:00	load_wx_FAI.cmd	=> dest table minuteweatherfg 15min weather2rf
REM -		xx+1:00	load_wx_Chu.cmd	=> dest table minuteweather 15min weather2
REM -		xx+1:00	load_wx_AQU.cmd => dest table wx_singlesensor1m  
REM -	Then at 10 sec intervals
REM -		xx+1:10	load_wx_SAR.php => dest table minuteweatherfg 15min weather2rf (15 min)
REM -		xx+1:20	load_wx_OAK.php => dest table minuteweather 15min weather 
REM -		xx+1:30	load_wx_Del.php => dest table minuteweather 15min weather 	(15 min)
REM -		xx+1:40	load_wx_BEL.php => dest table minuteweather 15min weather 
REM -		xx+1:50	load_wx_SAN.php => dest table minuteweather 15min weather
REM -	load_wx_KEE.php => currently disabled no 2nd station
REM -
REM -	xx+2:15	wx_splitter.php	
REM -	xx+2:35	load_wx_oo.php	

