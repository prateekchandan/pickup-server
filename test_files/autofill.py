import requests
import os
import json
loginURL = "http://pickup.prateekchandan.me/add_journey"
HTTPSession = requests.session()
count=8;
start_locations=['Larsen+Tourbo+Powai','IIT+Bombay+Hostel+9','Hiranandani+Hospital','Hiranandani','Kanjur+Marg+Station',
'Chandivali','Raheja+Vihar','Supreme+Powai','Galleria+Hiranandani','Powai+Plaza']
end_locations=['Thane+Station','Kalyan+Station','Mulund+Station','CST','Government+Colony+Bandra' , 'Bandra+Kurla+Complex'
'Sion+Station','Kandivali+Station','Goregaon+Mumbai' , 'Bandra+Railway']  
for k in range(48):
	hours='00'
	minutes='00'
	count=7;
	if (k/2)<10:
		hours='0'+str(k/2);
	else:
		hours=str(k/2);
	if k%2==0:
		minutes='00'
	else:
		minutes='30'
	for i in start_locations:
		for j in end_locations:
			count=count+1;
			startGet = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?address='+i).content)
			endGet = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?address='+j).content)
			startLat = startGet["results"][0]["geometry"]["location"]["lat"]
			startLng = startGet["results"][0]["geometry"]["location"]["lng"]
			endLat = endGet["results"][0]["geometry"]["location"]["lat"]
			endLng = endGet["results"][0]["geometry"]["location"]["lng"]
			startText = i;
			endText = j;
			if k%2==1:
				startText=j;
				endText=i;
				startLat = endGet["results"][0]["geometry"]["location"]["lat"]
				startLng = endGet["results"][0]["geometry"]["location"]["lng"]
				endLat = startGet["results"][0]["geometry"]["location"]["lat"]
				endLng = startGet["results"][0]["geometry"]["location"]["lng"]
			postData = {'key':'9f83c32cf3c9d529e' ,'user_id':count , 'start_lat':startLat , 'start_long':startLng , 'end_lat':endLat,
			'end_long':endLng , 'journey_time' : '2015-05-02 '+hours+':'+minutes+':00' , 'margin_after':'10' , 'margin_before':'10' ,
			'preference':'1' , 'start_text':i,'end_text':j}
			#print postData
			afterLoginPage = HTTPSession.post(loginURL, data = postData )
			print afterLoginPage.content


#afterLoginPage = HTTPSession.post(loginURL, data = postData )
getOutput = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?latlng=40.714224,-73.961452').content)
print getOutput['results'][0]['formatted_address']
#print afterLoginPage.content
