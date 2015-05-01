import requests
import os
import json
loginURL = "http://pickup.prateekchandan.me/add_journey"
HTTPSession = requests.session()
start_locations=['Larsen+Tourbo+Powai','IIT+Bombay+Hostel+9','Hiranandani+Hospital','Hiranandani','Kanjur+Marg+Station',
'Chandivali','Raheja+Vihar','Supreme+Powai',]
end_locations=['Thane+Station','Kalyan+Station','Mulund+Station','CST',]  

for i in start_locations:
	for j in end_locations:
		startGet = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?address='+i).content)
		endGet = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?address='+j).content)
		startLat = startGet["results"][0]["geometry"]["location"]["lat"]
		startLng = startGet["results"][0]["geometry"]["location"]["lng"]
		endLat = startGet["results"][0]["geometry"]["location"]["lat"]
		endLng = startGet["results"][0]["geometry"]["location"]["lng"]
		postData = {'key':'9f83c32cf3c9d529e' ,'user_id':'7' , 'start_lat':startLat , 'start_long':startLng , 'end_lat':endLat,
		'end_long':endLng , 'journey_time' : '2015-05-01 20:05:00' , 'margin_after':'10' , 'margin_before':'10' ,
		'preference':'1' , 'start_text':i,'end_text':j}
		print postData + '\n'

#afterLoginPage = HTTPSession.post(loginURL, data = postData )
getOutput = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?latlng=40.714224,-73.961452').content)
print getOutput['results'][0]['formatted_address']
#print afterLoginPage.content
