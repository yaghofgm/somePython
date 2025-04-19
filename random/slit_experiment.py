import numpy as np
import matplotlib.pyplot as plt
from ipywidgets import interact, FloatSlider, IntSlider

def multi_slit_diffraction_interactive(n_slits, wavelength, a_slit_width, d_slits, screen_distance, I0):
    y = np.linspace(-0.02, 0.02, 10000)  # 2 cm para cada lado
    beta = (a_slit_width * y) / (wavelength * screen_distance)
    delta = (np.pi * d_slits * y) / (wavelength * screen_distance)
    
    # Avoid division by zero in interference term
    with np.errstate(divide='ignore', invalid='ignore'):
        interference = np.sin(n_slits * delta) / np.sin(delta)
        interference[np.isnan(interference)] = n_slits  # sin(0)/sin(0) = N
        intensity = I0 * (np.sinc(beta))**2 * (interference)**2

    plt.figure(figsize=(10, 4))
    plt.plot(y * 1e3, intensity)
    plt.xlabel("Altura na tela (mm)")
    plt.ylabel("Intensidade (lux)")
    plt.title(f"Difração de {n_slits} fenda(s)")
    plt.grid(True)
    plt.tight_layout()
    plt.show()

# Sliders interativos
interact(
    multi_slit_diffraction_interactive,
    n_slits=IntSlider(value=1, min=1, max=100, step=1, description='n (fendas)'),
    d_slits=FloatSlider(value=0.5e-3, min=0.1e-3, max=1.0e-3, step=0.01e-3, description='d (m)'),
    wavelength=FloatSlider(value=532e-9, min=400e-9, max=700e-9, step=10e-9, description='λ (m)'),
    a_slit_width=FloatSlider(value=0.25e-3, min=0.05e-3, max=1.0e-3, step=0.01e-3, description='a (m)'),
    screen_distance=FloatSlider(value=1.8, min=0.5, max=5.0, step=0.1, description='L (m)'),
    I0=FloatSlider(value=1.0, min=0.1, max=5.0, step=0.1, description='E₀ (lux)')
)
