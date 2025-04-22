import numpy as np
import argparse as ap

parser = ap.ArgumentParser(description="Interference Angle Calculator")
parser.add_argument('--range', type=int, default=6, help='Range of modes for angle (pos and neg)')
args = parser.parse_args()

v=340
f = 444
lamb = v/f
d = 3.5 #m
#r = int(input("Enter the number of modes r \n"))
for m in np.arange(-args.range,args.range):
	sin_theta = (lamb/d * (m+1/4))
	if abs(sin_theta)<=1:
		angle_rad = np.arcsin(sin_theta)
		angle_deg = np.degrees(angle_rad)
		print(f"m={m:2d}, sin(θ) = {angle_rad:.4f},θ = {angle_deg:.2f}°")
