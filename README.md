Lifter

Quick'n dirty script to scrape a user's page at lift.do and 
create a csv file of a user's habit check-ins.

E.g. 
http://lift.do/users/5046d263bf6a2411642a
becomes                 
http://lifter.wanderingstan.com/users/5046d263bf6a2411642a
returns a csv file.
This can be dynamically linked to a google spreadsheet by putting this 
in a cell:
=ImportData("http://lifter.wanderingstan.com/users/5046d263bf6a2411642a")

Results are cached for 6 hours to prevent server overloading.
Optionally add &extract_numbers=1 to URL to extract any numerical 
information from comments. E.g. "Slept 6 hours" becomes "6".

By Stan James http://wanderingstan.com 
with help from Joel Longtine <jlongtine@gmail.com>
Thanks to the team at Lift.do for the great tool!