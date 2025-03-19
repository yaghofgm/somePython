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
ax.set_title(r'Drum Rim $z(\theta) = sin(\theta)+2sin(2\theta)$ for $r=1$')



theta = np.linspace(0, 2*np.pi, 300)
r = np.linspace(0, 1, 100)  

R, Theta = np.meshgrid(r, theta)

X = R * np.cos(Theta)
Y = R * np.sin(Theta)
Z = R * np.sin(Theta) + 2 * R**2 * np.sin(2*Theta)

fig = plt.figure(figsize=(10,8))
ax = fig.add_subplot(111, projection='3d')
ax.plot_surface(X, Y, Z, cmap='viridis', edgecolor='none')
ax.set_xlabel('X')
ax.set_ylabel('Y')
ax.set_zlabel('Z')
ax.set_title(r'Surface plot of $u(r, \theta) = r\sin(\theta) + 2r^2\sin(2\theta)$')

plt.show()

