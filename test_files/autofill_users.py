import requests
import os
import json
import time
loginURL = "http://pickup.prateekchandan.me/add_user"
HTTPSession = requests.session()
start_locations=['Larsen+Tourbo+Powai','IIT+Bombay+Hostel+9','Hiranandani+Hospital','Hiranandani','Kanjur+Marg+Station',
'Chandivali','Raheja+Vihar','Supreme+Powai','Galleria+Hiranandani','Powai+Plaza']
end_locations=['Andheri+West','Kalyan+Station','Mulund+Station','Chhatrapati+Shivaji+Terminus','Government+Colony+Bandra' , 'Bandra+Kurla+Complex',
'Sion+Station+Mumbai','Kandivali+Station','Goregaon+Mumbai' , 'Nariman+Point'] 
start_location_coord=['19.1215264,72.8920625', '19.135483,72.908181', '19.2526701,72.9801366', '19.1153798,72.9091436', '19.1301962,72.9284363', '19.1074911,72.9017603', '19.1190749,72.8951151', '19.1113422,72.9083873', '19.1193858,72.9116404', '19.122692,72.9132966']

end_location_coord=['19.189047,72.974972', '19.2354335,73.1298894', '19.172934,72.9570823', '18.9398208,72.8354676', '19.0644193,72.8493324', '19.0687893,72.8702647', '19.0475874,72.8640097', '19.2045159,72.8520095', '19.1551485,72.8678551', '19.059902,72.841504', '19.1363246,72.82766', '19.2354335,73.1298894', '19.172934,72.9570823', '18.9398208,72.8354676', '19.0644193,72.8493324', '19.0687893,72.8702647', '19.0475874,72.8640097', '19.2045159,72.8520095', '19.1551485,72.8678551', '18.9255728,72.8242221']

count=0;

for i in start_locations:
	startGet = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?address='+i).content)
	startLat = startGet["results"][0]["geometry"]["location"]["lat"]
	startLng = startGet["results"][0]["geometry"]["location"]["lng"]
	start_location_coord.append(str(startLat)+","+str(startLng))
	time.sleep(1)

for i in end_locations:
	startGet = json.loads(HTTPSession.get('https://maps.googleapis.com/maps/api/geocode/json?address='+i).content)
	startLat = startGet["results"][0]["geometry"]["location"]["lat"]
	startLng = startGet["results"][0]["geometry"]["location"]["lng"]
	print str(startLat)+","+str(startLng)
	end_location_coord.append(str(startLat)+","+str(startLng))
	time.sleep(1)
print start_location_coord
print end_location_coord

for i in range(100):
	postData = {'key':'9f83c32cf3c9d529e' ,'fbid':i ,'name':'person'+str(i) , 'email':'person'+str(i)+'@gmail.com' ,
	'device_id':i , 'gcm_id':i, 'mac_addr':i , 'gender':'male', 'leaving_home':'08:00:00', 'leaving_office':'17:00:00',
	'home_text':start_locations[int(i/10)],'office_text':end_locations[int(i%10)], 'home_location':start_location_coord[int(i/10)],
	'office_location':end_location_coord[i%10]};
	afterLoginPage = HTTPSession.post(loginURL, data = postData )
	print afterLoginPage.content
		

#afterLoginPage = HTTPSession.post(loginURL, data = postData )

#print afterLoginPage.content

