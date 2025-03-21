import matplotlib.pyplot as plt
import numpy as np

def f1(t):
    return np.exp(-500*t)*(-8*np.cos(866*t)-4.62*np.sin(866*t))+2

x=np.linspace(0,0.02,1000)
y1=f1(x)
# def f2(t):
#     return 6*(2*t-1)*np.exp(-2*t)
# y2=f2(x)
plt.plot(x,y1)
# plt.plot(x,y2)
plt.grid(True)
plt.show()  