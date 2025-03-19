import matplotlib.pyplot as plt
import numpy as np
from numpy import pi as pi
from numpy import cos as cos
from numpy import sin as sin
from mpl_toolkits.mplot3d import Axes3D

theta = np.linspace(0,2*pi,300)
r=1

x= r*cos(theta)
y= r*sin(theta)
z=sin(theta)+2*sin(2*theta)

fig = plt.figure(figsize=(8,8))
ax = fig.add_subplot(111, projection='3d')
ax.plot(x, y, z)
ax.set_xlabel('X Label')
ax.set_ylabel('Y Label')
ax.set_zlabel('Z Label')
ax.set_title('Drum Skin')
plt.show()

