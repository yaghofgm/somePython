import numpy as np
import matplotlib.pyplot as plt
from ipywidgets import interact, FloatSlider, IntSlider



def composition(E0,omega,phi):
    t= np.linspace(0, 6 * np.pi, 600)
    Ex = E0 * np.cos(omega * t)
    Ey = E0 * np.cos(omega * t + phi)
    
    plt.figure(figsize=(10, 5))
    plt.plot(Ex, Ey)
    plt.title('Electric Field Locus (Ellipse for phase difference ≠ 90°)')
    plt.xlabel('E_x')
    plt.ylabel('E_y')
    plt.grid(True)
    plt.axis('equal')
    plt.show()

# composition(E0, omega, t, -np.pi/3)

interact(
    composition,
    E0=FloatSlider(value=1.0, min=0.1, max=5.0, step=0.1, description='E₀ (lux)'),
    omega=FloatSlider(value=1.0, min=0.1, max=5.0, step=0.1, description='ω (rad/s)'),
    phi=FloatSlider(value=0.0, min=-np.pi, max=np.pi, step=0.1, description='φ (rad)'),
)