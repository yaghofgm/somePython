import numpy as np
import matplotlib.pyplot as plt
from ipywidgets import interact, FloatSlider, IntSlider

import warnings
warnings.filterwarnings("ignore") 
m=3
def f(t):
    return 0.75*(1-np.exp(-3.2*t))
def update_plot(t_val=0):
    t = np.linspace(0,m,100*m)
    y=f(t)

    y_val = f(t_val)

    plt.figure(figsize=(8,4))
    plt.plot(t,y,label=r"$f(t)=0.75\left(1 - \mathrm{e}^{-3.2t}\right)$")
    plt.scatter([t_val], [y_val], color='red', label=f"Point ({t_val:.1f}, {y_val:.3f})")
    plt.xlabel("t")
    plt.ylabel("f(t)")
    plt.title("Interactive Plot of f(t)")
    plt.legend()
    plt.grid(True)
    # plt.axis('equal')
    plt.show()

# Create the interactive widget
interact(update_plot, t_val=FloatSlider(min=0, max=m, step=0.01, value=0, description="t"))