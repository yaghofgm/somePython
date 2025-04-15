#!/usr/bin/env python3
"""
Analizador completo de préstamos para estudiantes de EduBridge

Este script combina las funcionalidades de db_calculateLoan.py y student_loan_analyzer.py
en un único archivo, manteniendo la conexión con MySQL.

INSTRUCCIONES DE USO:
--------------------
Ejemplo básico:
    python3 edubridge_loan_analyzer_complete.py --id 4 --country US

Ejemplo completo:
    python3 edubridge_loan_analyzer_complete.py --id 4 --country US --amount 259536 --percentage 0.35 --max_months 240 --ratio 2.0

Flags disponibles:
    -i, --id         : ID del estudiante a analizar
    -t, --threshold  : Umbral de ganancia para considerar un préstamo rentable (default: 1.1)
    -c, --country    : País para cálculos de riesgo (default: BR)
    -l, --list       : Listar todos los estudiantes disponibles
    -o, --output     : Guardar resultados en archivo CSV
    -a, --amount     : Monto específico de préstamo a analizar
    -p, --percentage : Porcentaje del salario mensual usado para pagar el préstamo (default: 0.15)
    -m, --max_months : Número máximo de meses para pagar el préstamo (default: 120)
    -r, --ratio      : Máxima proporción de pago total respecto al préstamo original (default: 1.8)
"""

import numpy as np
import pandas as pd
import argparse
import warnings
import mysql.connector
from ipywidgets import interact, FloatSlider, IntSlider, Dropdown
from IPython.display import display

warnings.filterwarnings("ignore")  # para no contaminar la salida con mensajes de matplotlib

# ====================================================
# FUNCIONES DE CONEXIÓN A LA BASE DE DATOS
# ====================================================

def get_db_connection():
    """
    Establece conexión con la base de datos MySQL.
    """
    try:
        conn = mysql.connector.connect(
            host='localhost',        # o IP del servidor
            user='root',
            password='',
            database='portalusuarios'
        )
        return conn
    except mysql.connector.Error as err:
        print(f"Error de conexión a la base de datos: {err}")
        return None

def get_all_students():
    """
    Obtiene la lista de todos los estudiantes y sus datos académicos
    para permitir la selección de un estudiante específico
    """
    conn = get_db_connection()
    if not conn:
        return []
    
    cursor = conn.cursor(dictionary=True)
    
    try:
        query = """
        SELECT 
            u.id, 
            CONCAT(u.nome, ' ', u.sobrenome) AS nombre_completo,
            pe.gpa, 
            pu.nome AS universidad,
            cu.nome_curso AS curso,
            cu.salario_esperado
        FROM usuarios u
        JOIN perfil_estudante pe ON u.id = pe.usuario_id
        LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
        LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
        WHERE u.categoria = 'estudante'
        ORDER BY u.nome
        """
        
        cursor.execute(query)
        students = cursor.fetchall()
        return students
    
    except mysql.connector.Error as err:
        print(f"Error al consultar estudiantes: {err}")
        return []
    
    finally:
        cursor.close()
        conn.close()

# ====================================================
# FUNCIONES DE CÁLCULO DE PRÉSTAMOS
# ====================================================

def test1(emprestimo, juros, GPA, sex, university, course, salary_percentage=0.15, max_months=120, max_payment_ratio=1.8):
    """
    Will see if the person can pay the loan in the specified timeframe without paying more than max_payment_ratio times the initial loan

    DISCLAIMER: 
    1. We are assuming a fixed interest rate for the entire period.
    2. We are assuming a fixed monthly payment of a percentage of the salary.
    
    Parameters:
    emprestimo (float): The loan amount
    juros (float): Monthly interest rate
    GPA (float): Student's GPA
    sex (string): Student's sex ('male' or 'female')
    university (string): University name
    course (string): Course name
    salary_percentage (float): Percentage of salary used for monthly payment (default: 0.15)
    max_months (int): Maximum number of months to pay the loan (default: 120)
    max_payment_ratio (float): Maximum ratio of total payment to loan amount (default: 1.8)
    
    Returns: 
    dict: Resultados del análisis del préstamo
    """
    def salary_vector_prediction(GPA, sex, university, course, months_needed=120):
        """
        Predicts a salary vector over specified months with stepwise (non-continuous) growth.
        The salary increases once per year and stays fixed for 12 months.
        
        Parameters:
        GPA (float): Student's GPA
        sex (string): Student's sex ('male' or 'female')
        university (string): University name
        course (string): Course name
        months_needed (int): Number of months to predict (default: 120)
        
        Returns:
        list: Vector of monthly salaries
        """
        
        def base_salary(GPA, sex, university, course):
            # Estos son salarios anuales
            salary_data = {
                "Tecnologico de Monterrey Estado de Mexico": {
                    "Ingenieria Mecanica": 10000,
                    "Ingeniería Quimica": 12500,
                    "Ingeniería Computacion y Tecnologias de la informacion": 15000,
                },
                "Anahuac": {
                    "Ingenieria Industrial": 10000,
                    "Ingenieria Mecanica": 10000,
                },
                "University of Michigan Ann Arbor": {
                    "Electrical & Computer Engineering": 77300,
                    "Mechanical Engineering": 89668,
                }
            }

            gpa_data = {
                "Tecnologico de Monterrey Estado de Mexico": {
                    "Ingenieria Mecanica": 3.4,
                    "Ingeniería Quimica": 3.4,
                    "Ingeniería Computacion y Tecnologias de la informacion": 3.4,
                },
                "Anahuac": {
                    "Ingenieria Industrial": 3.0,
                    "Ingenieria Mecanica": 3.0,
                },
                "University of Michigan Ann Arbor": {
                    "Electrical & Computer Engineering": 3.8,
                    "Mechanical Engineering": 3.8,
                }
            }

            # Obtener el salario anual base
            raw_salary = salary_data.get(university, {}).get(course, 50000) #default salary is 50000
            gpa_avg = gpa_data.get(university, {}).get(course, 3.5) #default GPA is 3.5

            if sex == "male":
                beta = 0.12
            elif sex == "female":
                beta = 0.12
            else:
                raise ValueError("Invalid value for 'sex'. It must be either 'male' or 'female'.")

            # Calcular el salario anual ajustado por GPA
            annual_salary = raw_salary * np.exp(beta * (GPA - gpa_avg))
            
            # Convertir a salario mensual
            monthly_salary = annual_salary / 12
            
            return monthly_salary

        def get_annual_growth(university, course):
            growth_data = {
                "University A": {
                    "Engineering": 0.10,
                    "Arts": 0.05,
                },
                "University B": {
                    "Engineering": 0.12,
                    "Arts": 0.07,
                },
            }
            return growth_data.get(university, {}).get(course, 0.1)

        # --- Core logic ---
        initial_monthly_salary = base_salary(GPA, sex, university, course)
        annual_growth = get_annual_growth(university, course)

        salary_vector = []
        current_salary = initial_monthly_salary

        for month in range(months_needed):
            salary_vector.append(current_salary)
            if (month + 1) % 12 == 0:  # increase salary once per year
                current_salary *= (1 + annual_growth)

        return salary_vector
    
    # Asegurarnos de que el vector de salarios sea suficientemente largo
    salary_vec = salary_vector_prediction(GPA, sex, university, course, months_needed=max_months)

    def update_divida(months, divida, salary_vec, juros):
        monthly_payment = salary_vec[months] * salary_percentage  # Percentage of income
        payment = min(monthly_payment, divida)
        divida = (divida - payment) * (1 + juros)

        return max(divida, 0)  

    divida_list = []
    total_pago_list = []
    divida = emprestimo
    total_pago = 0
    months = 0
    
    while divida > 0 and months < max_months and total_pago < emprestimo * max_payment_ratio:
        divida = update_divida(months, divida, salary_vec, juros)
        divida_list.append(divida)
        total_pago_list.append(total_pago)
        total_pago += salary_vec[months] * salary_percentage
        months += 1

    was_paid = divida_list[-1] == 0
    months_left = max(max_months-months, 0)
    expected_return = total_pago

    return {
        "was_paid": was_paid,
        "months_left": months_left,
        "expected_return": expected_return,
        "remaining_debt": divida_list[-1]
    }

def test2(threshold, emprestimo, expected_return, country="BR", risk1=None, risk2=None, risk3=None, risk4=None):
    """
    Check if the expected value is above the profit threshold 
    Expected Value = (1-risk) * (expected return) + (risk) *(-emprestimo)
    
    check Expected Value > threshold * emprestimo
    
    Parameters:
    threshold (float): The threshold for risk worthiness.
    emprestimo (float): The loan amount.
    expected_return (float): The expected return.
    
    Returns:
    dict: A dictionary containing the results of the risk analysis.
    """
    def risk(country, risk1=None, risk2=None, risk3=None, risk4=None):
        """
        Calculate the risk based on the country and other factors
        
        Parameters:
        emprestimo (float): The loan amount.
        expected_return (float): The expected return.
        
        Returns:
        float: The calculated risk.
        """
        # Default country-level risk estimates
        base_risk = {
            "BR": 0.35,
            "US": 0.12,
            "MX": 0.20,
        }
        return base_risk.get(country.upper(), 0.25)  # fallback: 25%

    risco = risk(country, risk1=None, risk2=None, risk3=None, risk4=None)
    expected_value = (1 - risco) * (expected_return) + risco * (-emprestimo)
    return {
        "worthy": expected_value > threshold * emprestimo,
        "expected_value profit": expected_value - emprestimo,
        "profit percentage": (expected_value - emprestimo) / emprestimo,
        "risk": risco
    }

def test12(**kwargs):
    """
    Wrapper function to run both test1 and test2 and return their results.

    Parameters:
    kwargs: Arguments for test1 and test2.
      Required: emprestimo, juros, GPA, sex, university, course, profit_threshold, country
      Optional: salary_percentage (default 0.15), max_months (default 120), max_payment_ratio (default 1.8)

    Returns:
    tuple: (result1, result2)
        result1:{
            "was_paid": bool,
            "months_left": int,
            "expected_return": float,
            "remaining_debt": float
        }
        result2:{
            "worthy": bool,
            "expected_value profit": float,
            "profit percentage": float,
            "risk": float
        }
    """
    # Get optional parameters with defaults
    salary_percentage = kwargs.get("salary_percentage", 0.15)
    max_months = kwargs.get("max_months", 120)
    max_payment_ratio = kwargs.get("max_payment_ratio", 1.8)
    
    # Run test1
    result1 = test1(
        emprestimo=kwargs["emprestimo"],
        juros=kwargs["juros"],
        GPA=kwargs["GPA"],
        sex=kwargs["sex"],
        university=kwargs["university"],
        course=kwargs["course"],
        salary_percentage=salary_percentage,
        max_months=max_months,
        max_payment_ratio=max_payment_ratio
    )

    # Run test2
    result2 = test2(
        threshold=kwargs["profit_threshold"],
        emprestimo=kwargs["emprestimo"],
        expected_return=result1["expected_return"],
        country=kwargs["country"]
    )

    return result1, result2

def getRange2Offer(**kwargs):
    """
    Get the range of values for the loan offer for a given interest value 
    
    Returns:
    interest_rates: Array of monthly interest rates (0.01 to 0.10)
    loan_range: List of valid loan amounts for each interest rate
    """
    loan_range = []
    interest_rates = np.arange(0.01, 0.101, 0.01)  # Monthly interest rates from 1% to 10%

    for juros in interest_rates:
        row = []  # Valores de empréstimo válidos para esta tasa de juros
        for loan in range(5000, 305000, 5000):
            kwargs["juros"] = juros
            kwargs["emprestimo"] = loan
            result1, result2 = test12(**kwargs)
            if result2["worthy"] and result1["was_paid"]:
                row.append(loan)
        loan_range.append(row)
    
    return interest_rates, loan_range

def detectar_subintervalos(lista):
    if not lista:
        return []

    subintervalos = []
    inicio = lista[0]
    fim = lista[0]

    for valor in lista[1:]:
        if valor == fim + 5000:
            fim = valor
        else:
            subintervalos.append(f"[{inicio}, {fim}]")
            inicio = fim = valor
    subintervalos.append(f"[{inicio}, {fim}]")
    return subintervalos

def student_loan_range_analysis(student_id=None, profit_threshold=1.1, country="BR", loan_amount=None, salary_percentage=0.15, max_months=120, max_payment_ratio=1.8):
    """
    Analiza los rangos de préstamos válidos para un estudiante específico
    basado en sus datos académicos.
    
    Parámetros:
    student_id (int): ID del estudiante a analizar
    profit_threshold (float): Umbral de ganancia para considerar un préstamo rentable
    country (str): País para cálculos de riesgo
    loan_amount (float): Monto específico de préstamo a analizar
    salary_percentage (float): Porcentaje del salario usado para pagar mensualmente (por defecto: 0.15)
    max_months (int): Número máximo de meses para pagar el préstamo (por defecto: 120)
    max_payment_ratio (float): Proporción máxima de pago total respecto al préstamo original (por defecto: 1.8)
    
    Retorna:
    DataFrame: Tabla con tasas de interés y rangos de préstamos válidos
    """
    # Si no se proporciona un ID, muestra la lista de estudiantes
    if student_id is None:
        students = get_all_students()
        if not students:
            print("No se encontraron estudiantes en la base de datos.")
            return None
        
        print("Estudiantes disponibles:")
        for i, student in enumerate(students):
            print(f"{i+1}. {student['nombre_completo']} - {student['universidad']} - {student['curso']}")
        
        try:
            selection = int(input("\nSeleccione un estudiante (número): ")) - 1
            if 0 <= selection < len(students):
                student = students[selection]
            else:
                print("Selección inválida.")
                return None
        except ValueError:
            print("Entrada inválida. Debe ingresar un número.")
            return None
    else:
        # Buscar estudiante por ID
        conn = get_db_connection()
        if not conn:
            return None
        
        cursor = conn.cursor(dictionary=True)
        
        try:
            query = """
            SELECT 
                u.id, 
                CONCAT(u.nome, ' ', u.sobrenome) AS nombre_completo,
                pe.gpa, 
                pu.nome AS universidad,
                cu.nome_curso AS curso,
                cu.salario_esperado,
                cu.custo_semestre
            FROM usuarios u
            JOIN perfil_estudante pe ON u.id = pe.usuario_id
            LEFT JOIN perfil_universidade pu ON pe.universidade_id = pu.id
            LEFT JOIN curso_universidade cu ON pe.curso_id = cu.id
            WHERE u.id = %s AND u.categoria = 'estudante'
            """
            
            cursor.execute(query, (student_id,))
            student = cursor.fetchone()
            
            if not student:
                print(f"No se encontró un estudiante con ID {student_id}.")
                return None
                
        except mysql.connector.Error as err:
            print(f"Error al consultar estudiante: {err}")
            return None
        
        finally:
            cursor.close()
            conn.close()
    
    # Analizar rangos para el estudiante seleccionado
    print(f"\nAnalizando préstamos para: {student['nombre_completo']}")
    print(f"Universidad: {student['universidad']}")
    print(f"Curso: {student['curso']}")
    print(f"GPA: {student['gpa']}")
    print(f"Salario esperado: {student['salario_esperado']}")
    
    # Usar un valor predeterminado para el sexo, ya que no existe en la base de datos
    sex = "male"  # Valor por defecto
    
    # Multiplicar el costo semestral por 8 para obtener el costo total de la carrera
    total_program_cost = None
    if 'custo_semestre' in student and student['custo_semestre']:
        total_program_cost = student['custo_semestre'] * 8  # 8 semestres = 4 años
        print(f"Costo semestral: {student['custo_semestre']}")
        print(f"Costo total estimado (8 semestres): {total_program_cost}")
        
        # Si no se proporciona un monto específico, usamos el costo total como valor por defecto
        if loan_amount is None:
            loan_amount = total_program_cost
    
    # Obtener rangos de préstamos válidos
    interest_rates, loan_ranges = getRange2Offer(
        GPA=float(student['gpa']) if student['gpa'] else 3.0,
        sex=sex,  # Usamos el valor predeterminado
        university=student['universidad'],
        course=student['curso'],
        profit_threshold=profit_threshold,
        country=country,
        salary_percentage=salary_percentage,
        max_months=max_months,
        max_payment_ratio=max_payment_ratio
    )
    
    # Formar intervalos formateados
    intervalos_formatados = []
    for row in loan_ranges:
        partes = detectar_subintervalos(row)
        if partes:
            intervalos_formatados.append(", ".join(partes))
        else:
            intervalos_formatados.append("Ninguno")
    
    # Crear DataFrame con resultados
    df = pd.DataFrame({
        "Tasa de Interés Mensual (%)": (interest_rates * 100).round(2),
        "Intervalos de Préstamos Válidos (US$)": intervalos_formatados
    })
    
    df.reset_index(drop=True, inplace=True)
    
    # Si se ha especificado un monto de préstamo específico, mostrar información detallada
    if loan_amount:
        print(f"\nAnálisis para préstamo de US$ {loan_amount:.2f}:")
        
        # Calcular el pago mensual inicial (salario mensual estimado * porcentaje)
        monthly_salary = student['salario_esperado'] / 12
        monthly_payment = monthly_salary * salary_percentage
        
        print(f"Con {salary_percentage*100:.0f}% del salario mensual (≈ US$ {monthly_payment:.2f}/mes) y plazo máximo de {max_months} meses")
        print(f"Proporción máxima de pago total: {max_payment_ratio:.2f}x el préstamo original")
        print("---------------------------------------------------")
        
        # Lista para guardar las opciones más cercanas
        best_options = []
        
        for i, (juros, ranges) in enumerate(zip(interest_rates, loan_ranges)):
            # Verificar si el monto está en alguno de los rangos o encontrar el más cercano
            if ranges:
                # Ordenar por cercanía al monto solicitado
                closest_loan = min(ranges, key=lambda x: abs(x - loan_amount))
                
                if closest_loan in ranges:
                    # Calcular información específica para este préstamo y tasa
                    kwargs = {
                        "GPA": float(student['gpa']) if student['gpa'] else 3.0,
                        "sex": sex,
                        "university": student['universidad'],
                        "course": student['curso'],
                        "profit_threshold": profit_threshold,
                        "country": country,
                        "juros": juros,
                        "emprestimo": closest_loan,
                        "salary_percentage": salary_percentage,
                        "max_months": max_months,
                        "max_payment_ratio": max_payment_ratio
                    }
                    
                    result1, result2 = test12(**kwargs)
                    
                    # Guardar los detalles para mostrar solo las mejores opciones
                    best_options.append({
                        "juros": juros,
                        "closest_loan": closest_loan,
                        "difference": abs(closest_loan - loan_amount),
                        "result1": result1,
                        "result2": result2
                    })
        
        # Ordenar por cercanía al monto solicitado
        best_options.sort(key=lambda x: x["difference"])
        
        # Mostrar solo las 3 mejores opciones
        for i, option in enumerate(best_options[:3]):
            juros = option["juros"]
            closest_loan = option["closest_loan"]
            result1 = option["result1"]
            result2 = option["result2"]
            
            # Calcular tasa anual equivalente para referencia
            tasa_anual_equivalente = ((1 + juros) ** 12) - 1
            
            print(f"\n{i+1}. Tasa de interés: {juros*100:.2f}% mensual ({tasa_anual_equivalente*100:.2f}% anual equivalente)")
            print(f"   Préstamo: US$ {closest_loan:.2f}" + (" (exacto)" if closest_loan == loan_amount else f" (cercano a US$ {loan_amount:.2f})"))
            
            # Información sobre tiempo de pago
            if result1["was_paid"]:
                months_to_pay = max_months - result1["months_left"]
                print(f"   ✅ Préstamo pagado en {months_to_pay} meses")
                # Mostrar el monto total pagado y la relación con el préstamo original
                total_pagado = result1["expected_return"]
                ratio_pagado = total_pagado / closest_loan
                print(f"   💵 Monto total pagado: US$ {total_pagado:.2f} ({ratio_pagado:.2f}x el préstamo original)")
            else:
                print(f"   ❌ El préstamo no se paga completamente en {max_months} meses. Deuda restante: US$ {result1['remaining_debt']:.2f}")
                # Mostrar el monto total pagado hasta el momento
                total_pagado = result1["expected_return"]
                ratio_pagado = total_pagado / closest_loan
                print(f"   💵 Monto pagado hasta el momento: US$ {total_pagado:.2f} ({ratio_pagado:.2f}x el préstamo original)")
            
            # Información sobre rentabilidad
            if result2["worthy"]:
                print(f"   ✅ El valor esperado está por encima del umbral de riesgo ({(profit_threshold-1)*100:.0f}%)")
            else:
                print(f"   ❌ El valor esperado NO supera el umbral de riesgo ({(profit_threshold-1)*100:.0f}%)")
            
            profit = result2["expected_value profit"]
            profit_percentage = result2["profit percentage"] * 100
            print(f"   💰 Beneficio esperado: US$ {profit:.2f} | Porcentaje de beneficio: {profit_percentage:.2f}%")
    
    return df

# ====================================================
# FUNCIONES INTERACTIVAS
# ====================================================

def interactive_student_analysis():
    """Función interactiva para análisis de estudiantes en Jupyter Notebook"""
    students = get_all_students()
    if not students:
        print("No se encontraron estudiantes en la base de datos.")
        return
    
    # Crear un diccionario para el dropdown
    student_options = {f"{s['nombre_completo']} ({s['universidad']})": s['id'] for s in students}
    
    # Definir la función interactiva
    @interact(
        student=Dropdown(options=student_options, description='Estudiante:'),
        profit_threshold=FloatSlider(min=1.0, max=1.5, step=0.05, value=1.1, description='Umbral de ganancia:'),
        country=Dropdown(options=['BR', 'US', 'MX'], description='País:')
    )
    def update(student, profit_threshold=1.1, country='BR'):
        df = student_loan_range_analysis(student_id=student, profit_threshold=profit_threshold, country=country)
        with pd.option_context('display.max_colwidth', None):
            display(df)

def analyze_student_by_id(student_id, profit_threshold=1.1, country='BR'):
    """Función para uso directo (no interactivo)"""
    df = student_loan_range_analysis(student_id=student_id, profit_threshold=profit_threshold, country=country)
    with pd.option_context('display.max_colwidth', None):
        display(df)

# ====================================================
# FUNCIÓN PRINCIPAL Y MANEJO DE ARGUMENTOS
# ====================================================

def main():
    """Función principal del script"""
    parser = argparse.ArgumentParser(description='Analizador de rangos de préstamos para estudiantes')
    parser.add_argument('-i', '--id', type=int, help='ID del estudiante a analizar')
    parser.add_argument('-t', '--threshold', type=float, default=1.1,
                        help='Umbral de ganancia para considerar un préstamo rentable (default: 1.1)')
    parser.add_argument('-c', '--country', type=str, default='BR',
                        help='País para cálculos de riesgo (default: BR)')
    parser.add_argument('-l', '--list', action='store_true',
                        help='Listar todos los estudiantes disponibles')
    parser.add_argument('-o', '--output', type=str,
                        help='Guardar resultados en archivo CSV')
    parser.add_argument('-a', '--amount', type=float,
                        help='Monto específico de préstamo a analizar')
    parser.add_argument('-p', '--percentage', type=float, default=0.15,
                        help='Porcentaje del salario mensual usado para pagar el préstamo (default: 0.15)')
    parser.add_argument('-m', '--max_months', type=int, default=120,
                        help='Número máximo de meses para pagar el préstamo (default: 120)')
    parser.add_argument('-r', '--ratio', type=float, default=1.8,
                        help='Máxima proporción de pago total respecto al préstamo original (default: 1.8)')
    
    args = parser.parse_args()
    
    # Listar estudiantes si se solicita
    if args.list:
        students = get_all_students()
        if not students:
            print("No se encontraron estudiantes en la base de datos.")
            return
        
        print("\nEstudiantes disponibles:")
        print("--------------------------")
        for student in students:
            print(f"ID: {student['id']} - {student['nombre_completo']} - {student['universidad']} - {student['curso']}")
        return
    
    # Analizar estudiante específico o mostrar listado interactivo
    df = student_loan_range_analysis(
        student_id=args.id,
        profit_threshold=args.threshold,
        country=args.country,
        loan_amount=args.amount,
        salary_percentage=args.percentage, 
        max_months=args.max_months,
        max_payment_ratio=args.ratio
    )
    
    if df is not None:
        # Mostrar resultados
        with pd.option_context('display.max_colwidth', None):
            print("\nResultados del análisis de préstamos:")
            print("-------------------------------------")
            print(df.to_string(index=False))
        
        # Guardar resultados si se solicita
        if args.output:
            df.to_csv(args.output, index=False)
            print(f"\nResultados guardados en: {args.output}")

if __name__ == "__main__":
    main()